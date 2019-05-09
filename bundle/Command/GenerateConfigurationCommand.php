<?php

namespace EzSystems\EzPlatformXmlTextFieldTypeBundle\Command;

use DOMDocument;
use DOMXpath;
use Exception;
use PDO;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDOStatement;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use EzSystems\EzPlatformRichText\eZ\RichText\Converter;

class GenerateConfigurationCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'ezxmltext:generate-custom-tags-configuration';

    /**
     * @var array
     */
    protected $options = [
        'config-file' => [
            'mode' => InputOption::VALUE_OPTIONAL,
            'description' => 'Path where configurations file will be generated',
            'default' => 'app/config/custom_tags.yml'
        ],
        'override-config' => [
            'mode' => InputOption::VALUE_NONE,
            'description' => 'If specified configuration file exists, this option is required to update it'
        ]
    ];

    /**
     * @var Connection
     */
    protected $db;

    /**
     * @var Converter
     */
    protected $converter;

    /**
     * @var SymfonyStyle
     */
    protected $io;

    /**
     * @param Connection $db
     * @param Converter $converter
     */
    public function __construct(Connection $db, Converter $converter)
    {
        $this->db = $db;
        $this->converter = $converter;

        parent::__construct();
    }

    /**
     * Configures the current command.
     */
    protected function configure(): void
    {
        $this->setDescription('Generates configuration for converted custom tags');

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

        $config = $this->getCustomTagsConfig();
        $this->saveConfig($file, $config);
        $this->io->success('Configurations are saved for ' . count($config) . ' custom tags into "' . $file . '"');
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
        $dir = dirname($file);
        if (is_writeable($dir) === false) {
            throw new Exception('Path "' . $dir .'" is not writable');
        }

        if (file_exists($file) && $override === false) {
            throw new Exception('File "' . $file .'" exists, please use --override-config option or provide another --config-file');
        }
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

        $statement = $this->getRichTextAttributes('eztemplate');
        $this->io->progressStart($statement->rowCount());
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $xml = new DOMDocument();
            $xml->loadXML($row['data_text']);
            $xpath = new DOMXpath($xml);

            $elements = $xpath->query("*[local-name()='eztemplate'][@name]");
            foreach ($elements as $element) {
                $tag = $element->getAttribute('name');

                if (isset($tags[$tag]) === false) {
                    $tags[$tag] = [];
                }

                $attributes= $xpath->query("*[local-name()='ezconfig']/*[local-name()='ezvalue'][@key]", $element);
                foreach ($attributes as $attribute) {
                    $attr = $attribute->getAttribute('key');

                    if (in_array($attr, $tags[$tag])) {
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
     * Fetches Rich Text attributes for published versions only.
     *
     * @param string|null $filterBy Is used to filter Rich Text attributes by content
     *
     * @return PDOStatement
     */
    protected function getRichTextAttributes(string $filterBy = null): PDOStatement
    {
        $query = $this->db->createQueryBuilder();
        $query->select('a.data_text', 'a.version', 'o.id')
            ->from('ezcontentobject_attribute', 'a')
            ->leftJoin('a', 'ezcontentobject', 'o', 'o.id = a.contentobject_id AND o.current_version = a.version')
            ->where($query->expr()->andx(
                $query->expr()->eq('a.data_type_string', ':data_type_string'),
                $query->expr()->isNotNull('o.id')
            ))
            ->orderBy('a.id')
            ->setParameter('data_type_string', 'ezrichtext');

        if ($filterBy !== null) {
            $condition = $query->expr()->like('a.data_text', ':custom_attributes_element');
            $query->andWhere($condition)->setParameter('custom_attributes_element', '%' . $filterBy . '%');
        }

        return $query->execute();
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

        $customTags = [];
        foreach ($config as $customTag => $attributes) {
            $customTags[$customTag] = [
                'template' => '@ezdesign/custom_tag/' . $customTag . '.html.twig',
                'icon' => '/bundles/app/img/custom-tag-icons.svg#' . $customTag,
                'is_inline' => false,
                'attributes' => []
            ];

            foreach ($attributes as $attribute) {
                $customTags[$customTag]['attributes'][$attribute] = ['type' => 'string'];
            }
        }

        $parameters['ezrichtext']['custom_tags'] = $customTags;
        $parameters['ezpublish'] = [
            'system' => [
                'default' => [
                    'fieldtypes' => [
                        'ezrichtext' => [
                            'custom_tags' => array_keys($customTags)
                        ]
                    ]
                ]
            ]
        ];

        $yaml = Yaml::dump($parameters, 7);

        file_put_contents($file, $yaml);
    }
}
