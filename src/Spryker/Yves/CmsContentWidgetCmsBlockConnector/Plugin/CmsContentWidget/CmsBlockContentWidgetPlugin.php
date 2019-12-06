<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Yves\CmsContentWidgetCmsBlockConnector\Plugin\CmsContentWidget;

use ArrayObject;
use DateTime;
use Generated\Shared\Transfer\CmsBlockGlossaryPlaceholderTransfer;
use Generated\Shared\Transfer\CmsBlockGlossaryTransfer;
use Generated\Shared\Transfer\CmsBlockTransfer;
use Spryker\Shared\CmsContentWidget\Dependency\CmsContentWidgetConfigurationProviderInterface;
use Spryker\Yves\CmsContentWidget\Dependency\CmsContentWidgetPluginInterface;
use Spryker\Yves\Kernel\AbstractPlugin;
use Twig\Environment;

/**
 * @method \Spryker\Yves\CmsContentWidgetCmsBlockConnector\CmsContentWidgetCmsBlockConnectorFactory getFactory()
 */
class CmsBlockContentWidgetPlugin extends AbstractPlugin implements CmsContentWidgetPluginInterface
{
    protected const SPY_CMS_BLOCK_GLOSSARY_KEY_MAPPINGS = 'SpyCmsBlockGlossaryKeyMappings';
    protected const PLACEHOLDER = 'placeholder';
    protected const GLOSSARY_KEY = 'GlossaryKey';
    protected const KEY = 'key';

    /**
     * @var \Spryker\Shared\CmsContentWidget\Dependency\CmsContentWidgetConfigurationProviderInterface
     */
    protected $widgetConfiguration;

    /**
     * @param \Spryker\Shared\CmsContentWidget\Dependency\CmsContentWidgetConfigurationProviderInterface $widgetConfiguration
     */
    public function __construct(CmsContentWidgetConfigurationProviderInterface $widgetConfiguration)
    {
        $this->widgetConfiguration = $widgetConfiguration;
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return callable
     */
    public function getContentWidgetFunction()
    {
        return [$this, 'contentWidgetFunction'];
    }

    /**
     * @param \Twig\Environment $twig
     * @param array $context
     * @param string[] $blockNames
     * @param string|null $templateIdentifier
     *
     * @return string
     */
    public function contentWidgetFunction(Environment $twig, array $context, array $blockNames, $templateIdentifier = null): string
    {
        $blocks = $this->getBlockDataByNames($blockNames);
        $templatePath = $this->resolveTemplatePath($templateIdentifier);
        $rendered = '';

        foreach ($blocks as $block) {
            $blockTransfer = $this->mapCmsBlockToTransfer($block);

            $isActive = $this->validateBlock($blockTransfer) && $this->validateDates($blockTransfer);

            if ($isActive) {
                $rendered .= $twig->render($templatePath, [
                    'placeholders' => $this->getPlaceholders($blockTransfer),
                    'cmsContent' => $block,
                ]);
            }
        }

        return $rendered;
    }

    /**
     * @param string|null $templateIdentifier
     *
     * @return string
     */
    protected function resolveTemplatePath(?string $templateIdentifier = null): string
    {
        $availableTemplates = $this->widgetConfiguration->getAvailableTemplates();
        if (!$templateIdentifier || !array_key_exists($templateIdentifier, $availableTemplates)) {
            $templateIdentifier = CmsContentWidgetConfigurationProviderInterface::DEFAULT_TEMPLATE_IDENTIFIER;
        }

        return $availableTemplates[$templateIdentifier];
    }

    /**
     * @param string[] $blockNames
     *
     * @return array
     */
    protected function getBlockDataByNames(array $blockNames): array
    {
        $blocks = $this->getFactory()
            ->getCmsBlockStorageClient()
            ->findBlocksByNames($blockNames, $this->getLocale(), $this->getApplication()['store']);

        return $blocks;
    }

    /**
     * @param \Generated\Shared\Transfer\CmsBlockTransfer $cmsBlockData
     *
     * @return bool
     */
    protected function validateBlock(CmsBlockTransfer $cmsBlockData): bool
    {
        return $cmsBlockData->getCmsBlockTemplate()->getTemplatePath() !== null;
    }

    /**
     * @param \Generated\Shared\Transfer\CmsBlockTransfer $cmsBlockTransfer
     *
     * @return bool
     */
    protected function validateDates(CmsBlockTransfer $cmsBlockTransfer): bool
    {
        $dateToCompare = new DateTime();

        if ($cmsBlockTransfer->getValidFrom() !== null) {
            $validFrom = new DateTime($cmsBlockTransfer->getValidFrom());

            if ($dateToCompare < $validFrom) {
                return false;
            }
        }

        if ($cmsBlockTransfer->getValidTo() !== null) {
            $validTo = new DateTime($cmsBlockTransfer->getValidTo());

            if ($dateToCompare > $validTo) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param \Generated\Shared\Transfer\CmsBlockTransfer $cmsBlockTransfer
     *
     * @return array
     */
    protected function getPlaceholders(CmsBlockTransfer $cmsBlockTransfer): array
    {
        $placeholders = [];
        foreach ($cmsBlockTransfer->getGlossary()->getGlossaryPlaceholders() as $cmsBlockGlossaryPlaceholderTransfer) {
            $placeholders[$cmsBlockGlossaryPlaceholderTransfer->getPlaceholder()] = $cmsBlockGlossaryPlaceholderTransfer->getTranslationKey();
        }

        return $placeholders;
    }

    /**
     * @param array $cmsBlock
     *
     * @return \Generated\Shared\Transfer\CmsBlockTransfer
     */
    protected function mapCmsBlockToTransfer(array $cmsBlock): CmsBlockTransfer
    {
        $cmsBlockTransfer = (new CmsBlockTransfer())->fromArray($cmsBlock, true);
        $cmsBlockGlossaryPlaceholderTransfers = new ArrayObject();

        foreach ($cmsBlock[static::SPY_CMS_BLOCK_GLOSSARY_KEY_MAPPINGS] as $mapping) {
            $cmsBlockGlossaryPlaceholderTransfer = (new CmsBlockGlossaryPlaceholderTransfer())
                ->setPlaceholder($mapping[static::PLACEHOLDER])
                ->setTranslationKey($mapping[static::GLOSSARY_KEY][static::KEY]);
            $cmsBlockGlossaryPlaceholderTransfers->append($cmsBlockGlossaryPlaceholderTransfer);
        }

        $cmsBlockTransfer->setGlossary(
            (new CmsBlockGlossaryTransfer())
                ->setGlossaryPlaceholders($cmsBlockGlossaryPlaceholderTransfers)
        );

        return $cmsBlockTransfer;
    }
}
