{extends file="parent:frontend/index/main-navigation.tpl"}

{block name='frontend_index_navigation_categories_top_entry'}
	{if !$sCategory.hideTop && !$nnwebCountCategoryArticlesHideEmpty[$sCategory.id]}
		<li class="navigation--entry{if $sCategory.flag} is--active{/if}" role="menuitem">
			{block name='frontend_index_navigation_categories_top_link'}
				<a class="navigation--link{if $sCategory.flag} is--active{/if}" href="{$sCategory.link}" title="{$sCategory.description}" itemprop="url"{if $sCategory.external && $sCategory.externalTarget} target="{$sCategory.externalTarget}"{/if}>
					<span itemprop="name">{$sCategory.description}</span>
					{if is_numeric($nnwebCountCategoryArticlesCounts[$sCategory.id]) && empty($nnwebCountCategoryArticlesHideZero[$sCategory.id]) && $nnwebCountCategoryArticles_showInMainNavigation}
						<span class="badge navigation--count">
							{$nnwebCountCategoryArticlesCounts[$sCategory.id]}
						</span>
					{/if}
				</a>
			{/block}
		</li>
	{/if}
{/block}