<?php
/**
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace EzSystems\EzPlatformXmlTextFieldTypeBundle\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConvertXmlTextToRichTextCommandSubProcess extends ConvertXmlTextToRichTextCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('ezxmltext:convert-to-richtext-sub-process')
            ->setDescription('internal command used by ezxmltext:convert-to-richtext')
            ->addOption(
                'offset',
                null,
                InputOption::VALUE_REQUIRED,
                'Offset'
            )
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_REQUIRED,
                'Limit'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->baseExecute($input, $output, $dryRun);

        $offset = $input->getOption('offset');
        $limit = $input->getOption('limit');

        $this->convertFields($dryRun, null, !$input->getOption('disable-duplicate-id-check'), !$input->getOption('disable-id-value-check'), $offset, $limit);
    }
}
