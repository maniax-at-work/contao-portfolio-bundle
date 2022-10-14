<?php

declare(strict_types=1);

/**
 * maniax-at-work.de Contao Portfolio Bundle for Contao Open Source CMS
 *
 * @copyright     Copyright (c) 2022, maniax-at-work.de
 * @author        maniax-at-work.de <https://www.maniax-at-work.de>
 * @link          https://github.com/maniaxatwork/
 */

namespace Maniax\ContaoPortfolio\EventListener\Contao\DCA;

use Composer\InstalledVersions;
use Contao\CoreBundle\Slug\Slug;
use Contao\CoreBundle\Util\PackageUtil;
use Contao\DataContainer;
use Contao\Input;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Maniax\ContaoPortfolio\Entity\TlManiaxContaoPortfolioSubCategory;
use Maniax\ContaoPortfolio\Entity\TlManiaxContaoPortfolioItem as TlManiaxContaoPortfolioItemEntity;
use Maniax\ContaoPortfolio\Helper\NumberHelper;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment as TwigEnvironment;

class TlManiaxContaoPortfolioItem
{
    protected ManagerRegistry $registry;

    protected Slug $slugGenerator;

    protected RequestStack $requestStack;

    protected TwigEnvironment $twig;

    public function __construct(
        ManagerRegistry $registry,
        Slug $slugGenerator,
        RequestStack $requestStack,
        TwigEnvironment $twig
    ) {
        $this->registry = $registry;
        $this->slugGenerator = $slugGenerator;
        $this->requestStack = $requestStack;
        $this->twig = $twig;
    }

    /**
     * @param mixed $varValue
     *
     * @throws Exception
     */
    public function aliasSaveCallback($varValue, DataContainer $dc): string
    {
        $portfolioItemRepository = $this->registry->getRepository(TlManiaxContaoPortfolioItemEntity::class);
        if ($dc->inputName === 'alias') {
            $title = $dc->activeRecord->title;
            $aliasExists = fn (string $alias): bool => $portfolioItemRepository->doesAliasExist($alias, (int) $dc->activeRecord->id);
        } else {
            $aliasExists = fn (string $alias): bool => $portfolioItemRepository->doesAliasExist($alias);
        }

        if (empty($varValue)) {
            $varValue = $this->slugGenerator->generate(
                $title,
                [],
                $aliasExists
            );
        } elseif (preg_match('/^[1-9]\d*$/', $varValue)) {
            throw new Exception(sprintf($GLOBALS['TL_LANG']['ERR']['aliasNumeric'], $varValue));
        } elseif ($aliasExists($varValue)) {
            throw new Exception(sprintf($GLOBALS['TL_LANG']['ERR']['aliasExists'], $varValue));
        }

        return $varValue;
    }

    public function subCategoryOptionsCallback(): array
    {
        $subCategoryRepository = $this->registry->getRepository(TlManiaxContaoPortfolioSubCategory::class);

        $subCategories = $subCategoryRepository->findAll();

        $return = [];
        foreach ($subCategories as $subCategory) {
            $categoryRepository = $this->registry->getRepository(TlManiaxContaoPortfolioCategory::class);

            $category = $categoryRepository->findPublishedById($subCategory->getPid()); 

            $return[$subCategory->getId()] = $category->getCategory()->getTitle().": ". $subCategory->getSubCategory()->getTitle();
        }

        return $return;
    }

    public function saveCallbackGlobal(DataContainer $dc): void
    {
        // Front end call
        if (!$dc instanceof DataContainer) {
            return;
        }

        if (!$dc->activeRecord) {
            return;
        }

        if (!empty(Input::post('published'))) {
            $itemRepository = $this->registry->getRepository(TlManiaxContaoPortfolioItemEntity::class);
            $item = $itemRepository->find($dc->activeRecord->id);
            $this->registry->getManager()->persist($item);
            $this->registry->getManager()->flush();
        }
    }

    public function onShowInfoCallback(DataContainer $dc = null): void
    {
        $GLOBALS['TL_CSS'][] = 'bundles/maniaxcontaoportfolio/dashboard.min.css';
        $info = $this->twig->render('@ManiaxContaoPortfolio/be_maniax_info.html.twig', [
            'version' => PackageUtil::getVersion('maniaxatwork/contao-portfolio-bundle'),
        ]);

        Message::addRaw($info);
    }

    public function getLanguages(): array
    {
        if (version_compare(InstalledVersions::getVersion('contao/core-bundle'), '4.13', '>=')) {
            return System::getContainer()->get('contao.intl.locales')->getLanguages();
        }

        return System::getLanguages();
    }

    public function labelCallback(array $row, string $label, DataContainer $dc, array $labels): string
    {
        $subCategories = [];
        $sCategories = $this->subCategoryOptionsCallback();
        $subCategoriesArr = StringUtil::deserialize($row['subCategory']);
        foreach ($subCategoriesArr as $subCategory) {
            $subCategories[] = $sCategories[$subCategory];
        }

        $label = '<h2>'.$row['title'].'</h2>';
        $label .= implode(' | ', $subCategories);
        return $label;
    }
}
