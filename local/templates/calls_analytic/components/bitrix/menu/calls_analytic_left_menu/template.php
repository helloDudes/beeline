<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>
<?$this->SetViewTarget("left_menu_toggle_button");?>
<ul class="nav navbar-nav">
	<li><a class="sidebar-control sidebar-main-toggle hidden-xs"><i class="icon-paragraph-justify3"></i></a></li>
</ul>
<?$this->EndViewTarget();?>
<div class="sidebar-category sidebar-category-visible">
	<div class="category-content no-padding">
		<ul class="navigation navigation-main navigation-accordion">
			<li class="navigation-header"><span>Меню</span> <i class="icon-menu" title="Main pages"></i></li>
			<?foreach($arResult as $arItem):?>
				<?if(!$arItem["IS_PARENT"]):?>
					<?if($arItem["DEPTH_LEVEL"]==1):?>
						<?=$str?>
						<?$str="";?>
						<li <?if($arItem["SELECTED"]):?>class="active-menu-item"<?endif;?>><a href="<?=$arItem["LINK"]?>"><i class="<?=$arItem["ICON"]?>"></i> <span><?=$arItem["TEXT"]?></span></a></li>
					<?else:?>
						<li <?if($arItem["SELECTED"]):?>class="active-menu-item"<?endif;?>><a href="<?=$arItem["LINK"]?>"><?=$arItem["TEXT"]?></a></li>
					<?endif;?>
				<?else:?>
					<li>
						<a href="<?=$arItem["LINK"]?>"><i class="icon-stack2"></i> <span><?=$arItem["TEXT"]?></span></a>
						<ul>
						<?$str = "</ul>";?>
				<?endif;?>
			<?endforeach;?>
		</ul>
	</div>
</div>
