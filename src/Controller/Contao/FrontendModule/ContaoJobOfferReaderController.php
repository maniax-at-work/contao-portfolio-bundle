<?php

declare(strict_types=1);

/**
 * maniax-at-work.de Contao Jobs Bundle for Contao Open Source CMS
 *
 * @copyright     Copyright (c) 2022, maniax-at-work.de
 * @author        maniax-at-work.de <https://www.maniax-at-work.de>
 * @link          https://github.com/maniaxatwork/
 */

namespace Maniax\ContaoJobs\Controller\Contao\FrontendModule;

use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\Date;
use Contao\Environment;
use Contao\FilesModel;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use Doctrine\Persistence\ManagerRegistry;
use Maniax\ContaoJobs\Entity\TlManiaxContaoJobsJobLocation;
use Maniax\ContaoJoContaoJobsbsBasic\Entity\TlManiaxContaoJobsOffer;
use Maniax\ContaoJobs\Entity\TlManiaxContaoJobsOfferTranslation;
use Maniax\ContaoJobs\GoogleForJobs\GoogleForJobs;
use Maniax\ContaoJobs\Helper\MetaFieldsHelper;
use Maniax\ContaoJobs\Helper\NumberHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * @FrontendModule("maniax_contao_jobs_offer_reader",
 *   category="plentaContaoJobs",
 *   template="mod_maniax_contao_jobs_offer_reader",
 *   renderer="forward"
 * )
 */
class ContaoJobOfferReaderController extends AbstractFrontendModuleController
{
    protected ManagerRegistry $registry;

    protected MetaFieldsHelper $metaFieldsHelper;

    protected GoogleForJobs $googleForJobs;

    protected RequestStack $requestStack;

    public function __construct(
        ManagerRegistry $registry,
        MetaFieldsHelper $metaFieldsHelper,
        GoogleForJobs $googleForJobs,
        RequestStack $requestStack
    ) {
        $this->registry = $registry;
        $this->metaFieldsHelper = $metaFieldsHelper;
        $this->googleForJobs = $googleForJobs;
        $this->requestStack = $requestStack;
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        /* @var PageModel $objPage */
        global $objPage;

        $parts = StringUtil::deserialize($model->maniaxContaoJobsTemplateParts);

        System::loadLanguageFile('tl_maniax_contao_jobs_offer');

        if (!\is_array($parts)) {
            $parts = [];
        }

        if (\in_array('backlink', $parts, true)) {
            $template->referer = 'javascript:history.go(-1)';
            $template->back = $GLOBALS['TL_LANG']['MSC']['goBack'];
        }

        $jobOfferRepository = $this->registry->getRepository(TlManiaxContaoJobsOffer::class);
        $jobOfferTranslationRepository = $this->registry->getRepository(TlManiaxContaoJobsOfferTranslation::class);

        $alias = Input::get('auto_item');

        $jobOffer = $jobOfferRepository->findPublishedByIdOrAlias($alias);

        if (null === $jobOffer) {
            $translation = $jobOfferTranslationRepository->findByAliasAndLanguage($alias, $request->getLocale());
            if ($translation) {
                $jobOffer = $translation->getOffer();
            }
        }

        if (null === $jobOffer) {
            throw new PageNotFoundException('Page not found: '.Environment::get('uri'));
        }

        $parentId = $jobOffer->getId();

        // Fill the template with data from the parent record
        $template->jobOffer = $jobOffer;
        $template->jobOfferMeta = $metaFields = $this->metaFieldsHelper->getMetaFields($jobOffer, $model->imgSize);
        $objPage->pageTitle = strip_tags(StringUtil::stripInsertTags($metaFields['title']));

        $content = '';

        if (\in_array('title', $parts, true)) {
            $template->headline = StringUtil::stripInsertTags($metaFields['title']);
            $template->hl = $model->maniaxContaoJobsHeadlineTag;
        }

        if (\in_array('image', $parts, true)) {
            $content .= $this->getImage($jobOffer, $model);
        }

        if (\in_array('elements', $parts, true)) {
            $content .= $this->getContentElements($request, $parentId);
        }

        if (\in_array('description', $parts, true)) {
            $content .= $this->getDescription($jobOffer);
        }

        if (\in_array('employmentType', $parts, true)) {
            $content .= $this->getEmploymentType($jobOffer);
        }

        if (\in_array('validThrough', $parts, true)) {
            $content .= $this->getValidThrough($jobOffer);
        }

        if (\in_array('salary', $parts, true)) {
            $content .= $this->getSalary($jobOffer);
        }

        if (\in_array('jobLocation', $parts, true)) {
            $content .= $this->getJobLocation($jobOffer, $model);
        }

        $template->content = $content;

        $StructuredData = $this->googleForJobs->generatestructuredData($jobOffer);

        if (null !== $StructuredData) {
            $GLOBALS['TL_BODY'][] = $StructuredData;
        }

        if ($jobOffer->getCssClass()) {
            $template->class .= ('' != $template->class ? ' ' : '').$jobOffer->getCssClass();
        }

        return $template->getResponse();
    }

    private function getContentElements($request, $parentId): ?string
    {
        $elements = ContentModel::findPublishedByPidAndTable($parentId, 'tl_maniax_contao_jobs_offer');

        if (null === $elements) {
            return null;
        }

        // The layout section is stored in a request attribute
        $section = $request->attributes->get('section', 'main');

        // Get the rendered content elements
        $content = '';

        foreach ($elements as $element) {
            $content .= Controller::getContentElement($element->id, $section);
        }

        return $content;
    }

    private function getImage($jobOffer, $model): ?string
    {
        if ($jobOffer->isAddImage()) {
            $template = new FrontendTemplate('maniax_contao_jobs_reader_image');
            $template->class = 'ce_image';
            $image = FilesModel::findByUuid(StringUtil::binToUuid($jobOffer->getSingleSRC()));
            if ($image) {
                Controller::addImageToTemplate($template, [
                    'singleSRC' => $image->path,
                    'size' => $model->imgSize,
                ]);
            }

            return $template->parse();
        }

        return '';
    }

    private function getDescription($jobOffer): ?string
    {
        $template = new FrontendTemplate('maniax_contao_jobs_reader_description');
        $template->text = $this->metaFieldsHelper->getMetaFields($jobOffer)['description'];
        $template->class = 'ce_text job_description';

        return $template->parse();
    }

    private function getEmploymentType($jobOffer): ?string
    {
        $template = new FrontendTemplate('maniax_contao_jobs_reader_attribute');
        $metaFields = $this->metaFieldsHelper->getMetaFields($jobOffer);
        $template->label = $GLOBALS['TL_LANG']['tl_maniax_contao_jobs_offer']['employmentType'][0];
        $template->value = $metaFields['employmentTypeFormatted'];
        $template->class = 'job_employment_type';

        return $template->parse();
    }

    private function getValidThrough($jobOffer): ?string
    {
        if ($jobOffer->getValidThrough()) {
            $template = new FrontendTemplate('maniax_contao_jobs_reader_attribute');
            $template->label = $GLOBALS['TL_LANG']['tl_maniax_contao_jobs_offer']['validThrough'][0];
            $template->value = Date::parse(Date::getNumericDatimFormat(), $jobOffer->getValidThrough());
            $template->class = 'job_valid_through';

            return $template->parse();
        }

        return '';
    }

    private function getJobLocation($jobOffer, $model): ?string
    {
        $template = new FrontendTemplate('maniax_contao_jobs_reader_job_location');

        $locationsArr = StringUtil::deserialize($jobOffer->getJobLocation());
        $locationRepo = $this->registry->getRepository(TlManiaxContaoJobsJobLocation::class);

        $organizations = [];
        $locationsTpl = [];
        $imgs = [];

        if (\is_array($locationsArr)) {
            $locations = $locationRepo->findByMultipleIds($locationsArr);
            foreach ($locations as $location) {
                $organization = $location->getOrganization();
                if (!\array_key_exists($organization->getId(), $organizations)) {
                    if ($model->plentaJobsBasicShowLogo && $organization->getLogo()) {
                        $imgTpl = new FrontendTemplate('ce_image');
                        $image = FilesModel::findByUuid(StringUtil::binToUuid($organization->getLogo()));
                        Controller::addImageToTemplate($imgTpl, [
                            'singleSRC' => $image->path,
                            'size' => [200, 200, 'proportional'],
                        ]);
                        $imgs[$organization->getId()] = $imgTpl->parse();
                    }
                    $organizations[$organization->getId()] = $location->getOrganization();
                    $locationsTpl[$organization->getId()] = [];
                }
                $locationsTpl[$organization->getId()][] = $location;
            }
        }

        $template->organizations = $organizations;
        $template->locations = $locationsTpl;
        $template->imgs = $imgs;
        $template->isRemote = $jobOffer->isRemote();
        $template->isOnlyRemote = $jobOffer->isOnlyRemote();

        return $template->parse();
    }

    private function getSalary(TlManiaxContaoJobsOffer $jobOffer)
    {
        if ($jobOffer->isAddSalary()) {
            $numberHelper = new NumberHelper($jobOffer->getSalaryCurrency(), $this->requestStack->getCurrentRequest()->getLocale());
            $template = new FrontendTemplate('maniax_contao_jobs_reader_salary');
            $salary = [];

            if ($jobOffer->getSalaryValue() > 0) {
                $salary[] = $numberHelper->formatCurrency($jobOffer->getSalaryValue());
            }

            if ($jobOffer->getSalaryMaxValue() > 0) {
                $salary[] = $numberHelper->formatCurrency($jobOffer->getSalaryMaxValue());
            }

            if (empty($salary)) {
                return '';
            }

            $template->salary = implode(' - ', $salary);
            $template->unit = $GLOBALS['TL_LANG']['tl_maniax_contao_jobs_offer']['salaryUnits'][$jobOffer->getSalaryUnit()];

            return $template->parse();
        }

        return '';
    }
}
