<?php

namespace nnwebCountCategoryArticles;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\NativeQuery;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Query\ResultSetMapping;
use Shopware\Components\Model\QueryBuilder;
use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Components\Plugin\Context\UpdateContext;
use Shopware\Models\Article\Article;
use Shopware\Models\Category\Category;

class nnwebCountCategoryArticles extends Plugin {

	public static function getSubscribedEvents() {
		return [
			'Enlight_Controller_Action_PostDispatchSecure_Frontend' => 'onFrontendPostDispatch',
			'Enlight_Controller_Action_PostDispatchSecure_Widgets'  => 'onFrontendPostDispatch',
			'Theme_Compiler_Collect_Plugin_Less'                    => 'addLessFiles',
		];
	}

	public function update(UpdateContext $context) {
		$context->scheduleClearCache(InstallContext::CACHE_LIST_ALL);
		parent::update($context);
	}

	public function install(InstallContext $context) {
		$service = $this->container->get('shopware_attribute.crud_service');

		$service->update(
			's_categories_attributes',
			'nnwebCCAshowCount',
			'boolean',
			[
				'label'            => 'Zeige Anzahl der Artikel',
				'supportText'      => 'Die Anzahl der Artikel wird im Menü an der Kategorie angezeigt.',
				'translatable'     => true,
				'displayInBackend' => true,
				'position'         => 10,
			]
		);

		$service->update(
			's_categories_attributes',
			'nnwebCCAhideZero',
			'boolean',
			[
				'label'            => 'Bei leeren Kategorien ausblenden',
				'supportText'      => 'Ist die Anzahl 0 wird sie im Menü an der Kategorie NICHT angezeigt.',
				'translatable'     => true,
				'displayInBackend' => true,
				'position'         => 11,
			]
		);

		$service->update(
			's_categories_attributes',
			'nnwebCCAhideEmpty',
			'boolean',
			[
				'label'            => 'Leere Kategorien ausblenden',
				'supportText'      => 'Ist die Kategorie leer, wird sie in der Navigation komplett ausgeblendet.',
				'translatable'     => true,
				'displayInBackend' => true,
				'position'         => 12,
			]
		);

		$this->deleteCacheAndGenerateModel(
			[
				's_categories_attributes',
			]
		);

		$context->scheduleClearCache(InstallContext::CACHE_LIST_ALL);
	}

	public function uninstall(UninstallContext $context) {
		$service = $this->container->get('shopware_attribute.crud_service');
		$service->delete('s_categories_attributes', 'nnwebCCAshowCount');
		$service->delete('s_categories_attributes', 'nnwebCCAhideZero');
		$service->delete('s_categories_attributes', 'nnwebCCAhideEmpty');
		$this->deleteCacheAndGenerateModel(
			[
				's_categories_attributes',
			]
		);

		$context->scheduleClearCache(InstallContext::CACHE_LIST_ALL);
	}

	private function deleteCacheAndGenerateModel($tables) {
		$metaDataCache = Shopware()->Models()->getConfiguration()->getMetadataCacheImpl();
		$metaDataCache->deleteAll();
		Shopware()->Models()->generateAttributeModels($tables);
	}

	public function addLessFiles(\Enlight_Event_EventArgs $args) {
		$less = new \Shopware\Components\Theme\LessDefinition(
			[], [
			__DIR__ . '/Resources/views/frontend/_public/src/less/all.less',
		], __DIR__
		);

		return new ArrayCollection(
			[
				$less,
			]
		);
	}

	public function onFrontendPostDispatch(\Enlight_Controller_ActionEventArgs $args) {
        $config = $this->container->get('shopware.plugin.cached_config_reader')->getByPluginName($this->getName());

		$controller = $args->get('subject');
		$view = $controller->View();

		$categories = Shopware()->Modules()->Categories()->sGetWholeCategoryTree();

		$return = self::readOutInfosFromCategories($categories);

		$view->assign('nnwebCountCategoryArticlesCounts', $return["counts"]);
		$view->assign('nnwebCountCategoryArticlesHideEmpty', $return["hideEmpty"]);
		$view->assign('nnwebCountCategoryArticlesHideZero', $return["hideZero"]);

        $view->assign('nnwebCountCategoryArticles_showInMainNavigation', $config["nnwebCountCategoryArticles_showInMainNavigation"]);
        $view->assign('nnwebCountCategoryArticles_showInSidebarCategories', $config["nnwebCountCategoryArticles_showInSidebarCategories"]);

		$this->container->get('template')->addTemplateDir($this->getPath() . '/Resources/views/');
	}

	private function readOutInfosFromCategories($categories) {
		$customerGroupId = Shopware()->Shop()->getCustomerGroup()->getId();
		$counts = [];
		$hideEmpty = [];
		$hideZero = [];

		/** @var Category $category */
		foreach ($categories as $category) {
			if (
				is_array($category['attribute']) &&
				(!empty($category['attribute']['nnwebccashowcount']) || !empty($category['attribute']['nnwebccahideempty']))
			) {

				$countArticles = self::countArticlesByCategoryId($category['id'], $customerGroupId);

				if ($category['attribute']['nnwebccashowcount']) {
					$counts[$category['id']] = $countArticles;
				}

				if (empty($countArticles)) {
					if ($category['attribute']['nnwebccahideempty']) {
						$hideEmpty[$category['id']] = true;
					}
					if ($category['attribute']['nnwebccahidezero']) {
						$hideZero[$category['id']] = true;
					}
				}
			}

			if (!empty($category["sub"])) {
				$return = self::readOutInfosFromCategories($category["sub"]);
				$counts = $counts + $return["counts"];
				$hideEmpty = $hideEmpty + $return["hideEmpty"];
				$hideZero = $hideZero + $return["hideZero"];
			}
		}

		return [
			'counts'    => $counts,
			'hideEmpty' => $hideEmpty,
			'hideZero'  => $hideZero,
		];
	}

	/**
	 * @param                            $categoryId
	 * @param                            $customerGroupId
	 *
	 * @return array
	 */
	private static function countArticlesByCategoryId($categoryId, $customerGroupId) {
		$parameters = [
			'categoryId' => $categoryId,
			'customerGroupId' => $customerGroupId
		];

		return Shopware()->Models()->getConnection()->fetchColumn("
			SELECT COUNT(DISTINCT s0_.id)
			FROM s_articles s0_ 
			INNER JOIN s_articles_categories_ro s2_ ON s0_.id = s2_.articleID 
			INNER JOIN s_categories s1_ ON s1_.id = s2_.categoryID AND (s1_.active = 1 AND s1_.id = :categoryId) 
			LEFT JOIN s_articles_avoid_customergroups s4_ ON s0_.id = s4_.articleID  AND (s4_.customergroupID = :customerGroupId) 
			WHERE s0_.active = 1
			GROUP BY s4_.customergroupID, s1_.id 
			HAVING COUNT(s4_.customergroupID) = 0
		", $parameters);
	}
}
