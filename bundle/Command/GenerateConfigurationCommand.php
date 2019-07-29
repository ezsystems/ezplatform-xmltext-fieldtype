<?php

namespace EzSystems\EzPlatformXmlTextFieldTypeBundle\Command;

use DOMDocument;
use DOMXpath;
use Exception;
use PDO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use EzSystems\EzPlatformRichText\eZ\RichText\Converter;
use eZ\Publish\Core\FieldType\XmlText\Persistence\Legacy\ContentModelGateway as Gateway;

class GenerateConfigurationCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'ezxmltext:generate-configuration';

    /**
     * https://doc.ezplatform.com/en/latest/guide/extending_online_editor/#custom-data-attributes-and-classes.
     *
     * @var array
     */
    protected static $elementsMap = [
        'ul' => 'ul',
        'ol' => 'ol',
        'li' => 'li',
        'p' => 'paragraph',
        'table' => 'table',
        'tr' => 'tr',
        'td' => 'td',
    ];

    /**
     * @var array
     */
    protected $options = [
        'config-file' => [
            'mode' => InputOption::VALUE_OPTIONAL,
            'description' => 'Path where configurations file will be generated',
            'default' => 'app/config/custom_tags.yml',
        ],
        'override-config' => [
            'mode' => InputOption::VALUE_NONE,
            'description' => 'If specified configuration file exists, this option is required to update it',
        ],
        'skip-custom-classes' => [
            'mode' => InputOption::VALUE_NONE,
            'description' => 'Custom classes will be skipped, if this option is set',
        ],
        'skip-custom-attributes' => [
            'mode' => InputOption::VALUE_NONE,
            'description' => 'Custom attributes will be skipped, if this option is set',
        ],
        'skip-custom-tags' => [
            'mode' => InputOption::VALUE_NONE,
            'description' => 'Custom tags will be skipped, if this option is set',
        ],
    ];

    /**
     * @var Gateway
     */
    private $gateway;

    /**
     * @var Converter
     */
    private $converter;

    /**
     * @var SymfonyStyle
     */
    protected $io;

    /**
     * @param Gateway $gateway
     * @param Converter $converter
     */
    public function __construct(Gateway $gateway, Converter $converter)
    {
        $this->gateway = $gateway;
        $this->converter = $converter;

        parent::__construct();
    }

    /**
     * Configures the current command.
     */
    protected function configure(): void
    {
        $this->setDescription('Generates Rich Text configuration');

        foreach ($this->options as $name => $info) {
            $default = $info['default'] ?? null;
            $this->addOption($name, null, $info['mode'], $info['description'], $default);
        }
    }

    /**
     * Executes the current command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|null null or 0 if everything went fine, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        $file = $input->getOption('config-file');
        $override = $input->getOption('override-config');
        try {
            $this->checkConfigFile($file, $override);
        } catch (Exception $e) {
            $this->io->error($e->getMessage());

            return;
        }

        $skipCustomClasses = $input->getOption('skip-custom-classes');
        $skipCustomAttributes = $input->getOption('skip-custom-attributes');
        $skipCustomTags = $input->getOption('skip-custom-tags');
        if ($skipCustomClasses && $skipCustomAttributes && $skipCustomTags) {
            $this->io->error('There is nothing to process as custom attributes/classes and custom tags are skipped');

            return;
        }

        $config = $this->getConfig($skipCustomClasses, $skipCustomAttributes, $skipCustomTags);
        $lines = [
            $this->getConfigStatistics($config['custom_classes'] ?? [], 'custom classes'),
            $this->getConfigStatistics($config['custom_attributes'] ?? [], 'custom attributes'),
            $this->getConfigStatistics($config['custom_tags'] ?? [], 'custom tags'),
        ];
        foreach ($lines as $line) {
            $this->io->note($line);
        }

        if (\count($config) > 0) {
            $this->saveConfig($file, $config);
            $this->io->success('Configurations are saved into "' . $file . '"');
        }
    }

    /**
     * Returns string statistics for config definitions.
     *
     * @param array $definitions
     * @param string $title
     *
     * @return string
     */
    protected function getConfigStatistics(array $definitions, string $title): string
    {
        $count = \count($definitions);
        $output = 'Found ' . $count . ' elements with ' . $title;

        if ($count > 0) {
            $elements = \is_array(array_values($definitions)[0])
                ? array_keys($definitions)
                : $definitions;
            $output .= ': ' . implode(', ', $elements);
        }

        return $output;
    }

    /**
     * Checks if results can be stored in specified configuration file.
     *
     * @param string $file
     * @param bool $override
     *
     * @throws Exception
     */
    protected function checkConfigFile(string $file, bool $override = false): void
    {
        $dir = \dirname($file);
        if (is_writable($dir) === false) {
            throw new Exception('Path "' . $dir . '" is not writable');
        }

        if (file_exists($file) && $override === false) {
            throw new Exception('File "' . $file . '" exists, please use --override-config option or provide another --config-file');
        }
    }

    /**
     * Fetches the configuration.
     *
     * @param bool $skipClasses
     * @param bool $skipAttributes
     * @param bool $skipCustomTags
     *
     * @return array
     */
    protected function getConfig(
        bool $skipClasses = false,
        bool $skipAttributes = false,
        bool $skipCustomTags = false
    ): array {
        $config = [];

        if ($skipClasses === false) {
            $config['custom_classes'] = $this->getCustomClassesConfig();
        }

        if ($skipAttributes === false) {
            $config['custom_attributes'] = $this->getCustomAttributesConfig();
        }

        if ($skipCustomTags === false) {
            $config['custom_tags'] = $this->getCustomTagsConfig();
        }

        return $config;
    }

    /**
     * Fetches the configuration for custom classes.
     *
     * @return array
     */
    protected function getCustomClassesConfig(): array
    {
        $this->io->title('Extracting Custom Classes from Rich Text fields');

        $classes = [];
        $excludeElements = ['div', 'span'];

        $statement = $this->gateway->getRichTextAttributes('class');
        $this->io->progressStart($statement->rowCount());
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            try {
                $xpath = $this->getRichTextContentXpath($row['data_text']);
            } catch (Exception $e) {
                continue;
            }

            $elements = $xpath->query('//*[@class]');
            foreach ($elements as $element) {
                $tag = $element->nodeName;
                if (\in_array($tag, $excludeElements)) {
                    continue;
                }

                $elementCssClasses = explode(' ', $element->getAttribute('class'));
                foreach ($elementCssClasses as $class) {
                    if (isset($classes[$tag]) === false) {
                        $classes[$tag] = [];
                    }

                    if (\in_array($class, $classes[$tag])) {
                        continue;
                    }

                    $classes[$tag][] = $class;
                }
            }

            $this->io->progressAdvance();
        }

        $this->io->progressFinish();

        return $classes;
    }

    /**
     * Fetches the configuration for custom attributes.
     *
     * @return array
     */
    protected function getCustomAttributesConfig(): array
    {
        $this->io->title('Extracting Custom Attributes from Rich Text fields');

        $attributes = [];

        $statement = $this->gateway->getRichTextAttributes('ezvalue');
        $this->io->progressStart($statement->rowCount());
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            try {
                $xpath = $this->getRichTextContentXpath($row['data_text']);
            } catch (Exception $e) {
                continue;
            }

            $elements = $xpath->query("//@*[starts-with(local-name(),'data-ezattribute-')]");
            foreach ($elements as $element) {
                $attr = str_replace('data-ezattribute-', '', $element->nodeName);
                $tag = $element->parentNode->nodeName;

                if (isset($attributes[$tag]) === false) {
                    $attributes[$tag] = [];
                }

                if (isset($attributes[$tag][$attr])) {
                    continue;
                }

                $attributes[$tag][$attr] = ['type' => 'text'];
            }

            $this->io->progressAdvance();
        }

        $this->io->progressFinish();

        return $attributes;
    }

    /**
     * Fetches the configuration for custom tags.
     *
     * @return array
     */
    protected function getCustomTagsConfig(): array
    {
        $this->io->title('Extracting Custom Tags from Rich Text fields');

        $tags = [];

        $statement = $this->gateway->getRichTextAttributes('eztemplate');
        $this->io->progressStart($statement->rowCount());
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $xpath = $this->getRichTextContentXpath($row['data_text'], false);
            $elements = $xpath->query("*[local-name()='eztemplate'][@name]");

            foreach ($elements as $element) {
                $tag = $element->getAttribute('name');

                if (isset($tags[$tag]) === false) {
                    $tags[$tag] = [];
                }

                $attributes = $xpath->query("*[local-name()='ezconfig']/*[local-name()='ezvalue'][@key]", $element);
                foreach ($attributes as $attribute) {
                    $attr = $attribute->getAttribute('key');

                    if (\in_array($attr, $tags[$tag])) {
                        continue;
                    }

                    $tags[$tag][] = $attr;
                }
            }

            $this->io->progressAdvance();
        }

        $this->io->progressFinish();

        return $tags;
    }

    /**
     * Converts Rich Text content to HTML5 edit, and get its DOMXpath object.
     *
     * @param string $content XML content
     * @param bool $htmlEdit
     *
     * @return DOMXpath
     */
    protected function getRichTextContentXpath(string $content, bool $htmlEdit = true): DOMXpath
    {
        $xml = new DOMDocument();
        $xml->loadXML($content);

        if ($htmlEdit) {
            $xml = $this->converter->convert($xml);
        }

        return new DOMXpath($xml);
    }

    /**
     * Saves the config.
     *
     * @param string $file
     * @param array $config
     */
    protected function saveConfig(string $file, array $config): void
    {
        $parameters = [];
        $richTextParams = [];

        // Custom Classes
        if (isset($config['custom_classes']) && \count($config['custom_classes']) > 0) {
            $customClasses = [];
            foreach ($config['custom_classes'] as $element => $classes) {
                if (isset(self::$elementsMap[$element]) === false) {
                    continue;
                }

                $customClasses[self::$elementsMap[$element]] = [
                    'choices' => $classes,
                    'default_value' => $classes[0],
                    'required' => 'false',
                    'multiple' => 'false',
                ];
            }

            if (\count($customClasses) > 0) {
                $richTextParams['classes'] = $customClasses;
            }
        }

        // Custom Attributes
        if (isset($config['custom_attributes']) && \count($config['custom_attributes']) > 0) {
            $customAttributes = [];
            foreach ($config['custom_attributes'] as $element => $attributes) {
                if (isset(self::$elementsMap[$element]) === false) {
                    continue;
                }

                $customAttributes[self::$elementsMap[$element]] = $attributes;
            }

            if (\count($customAttributes) > 0) {
                $richTextParams['attributes'] = $customAttributes;
            }
        }

        // Custom Tags
        if (isset($config['custom_tags']) && \count($config['custom_tags']) > 0) {
            $customTags = [];
            foreach ($config['custom_tags'] as $customTag => $attributes) {
                $customTags[$customTag] = [
                    'template' => '@ezdesign/custom_tag/' . $customTag . '.html.twig',
                    'icon' => '/bundles/app/img/custom-tag-icons.svg#' . $customTag,
                    'is_inline' => false,
                    'attributes' => [],
                ];

                foreach ($attributes as $attribute) {
                    $customTags[$customTag]['attributes'][$attribute] = ['type' => 'string'];
                }
            }

            if (\count($customTags) > 0) {
                $parameters['ezrichtext']['custom_tags'] = $customTags;
                $richTextParams['custom_tags'] = array_keys($customTags);
            }
        }

        $parameters['ezpublish'] = [
            'system' => [
                'default' => [
                    'fieldtypes' => [
                        'ezrichtext' => $richTextParams,
                    ],
                ],
            ],
        ];

        $yaml = Yaml::dump($parameters, 8);

        file_put_contents($file, $yaml);
    }
}
