<?
if(!$USER->IsAuthorized() && $_SERVER["REQUEST_URI"]!="/calls_analytic/" && $_SERVER["REQUEST_URI"]!="/calls_analytic/testindex.php")
  LocalRedirect("/calls_analytic/");
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="shortcut icon" href="/bitrix/templates/.default/img/favicon.ico" type="image/x-icon">
	<?$APPLICATION->ShowHead();?>
	<?$APPLICATION->SetTitle("Рабочий стол")?>
	<title><?$APPLICATION->ShowTitle()?></title>
	<!-- Global stylesheets -->
  <?$APPLICATION->SetAdditionalCSS("https://fonts.googleapis.com/css?family=Roboto:400,300,100,500,700,900");?>
  <?$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH."/assets/css/icons/icomoon/styles.css");?>
  <?$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH."/assets/css/bootstrap.css");?>
  <?$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH."/assets/css/core.css");?>
  <?$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH."/assets/css/components.css");?>
  <?$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH."/assets/css/colors.css");?>
	<!-- /global stylesheets -->

	<!-- Core JS files -->

  <?$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH."/assets/js/plugins/loaders/pace.min.js");?>
  <?$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH."/assets/js/core/libraries/jquery.min.js");?>
  <?$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH."/assets/js/core/libraries/bootstrap.min.js");?>
  <?$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH."/assets/js/plugins/loaders/blockui.min.js");?>
	<!-- /core JS files -->

	<?$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH."/assets/js/core/app.js");?>
	<?$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH."/include/js/clarification-popup.js");?>
</head>

<body>
<?$APPLICATION->ShowPanel();?>
	<!-- Main navbar -->
	<div class="navbar navbar-inverse">

		<div class="navbar-header">
			<a href="/"><img class="ATC-logo" src="<?=SITE_TEMPLATE_PATH?>/images/logo-n60.png" alt=""></a>

			<ul class="nav navbar-nav visible-xs-block">
				<?if($USER->IsAuthorized()):?>
          <li><a data-toggle="collapse" data-target="#navbar-mobile"><i class="icon-tree5"></i></a></li>
					<li><a class="sidebar-mobile-main-toggle"><i class="icon-paragraph-justify3"></i></a></li>
				<?endif;?>
        <?if(!$USER->IsAuthorized()):?>
				  <li><a class="come_in_button">Войти</a></li>
        <?endif;?>
			</ul>
		</div>

		<div class="navbar-collapse collapse" id="navbar-mobile">
			<?$APPLICATION->ShowViewContent("left_menu_toggle_button")?>

			<?$APPLICATION->ShowViewContent("online")?>

			<ul class="nav navbar-nav navbar-right">

				<?$APPLICATION->IncludeComponent(
	"dmm:dmm.auth",
	".default",
	array(
		"COMPONENT_TEMPLATE" => ".default",
		"LOGOUT_URL" => "/calls_analytic/include/logout.php",
		"SETTINGS_URL" => "/calls_analytic/integration/smartSMB.php",
		"REGISTRATION_URL" => "/calls_analytic/registration.php",
		"ADMIN_URL" => "/calls_analytic/calls_admin/"
	),
	false
);?>
			</ul>
		</div>
	</div>
	<!-- /main navbar -->

	<!-- Page container -->
	<div class="page-container">

		<!-- Page content -->
		<div class="page-content">
			<?if($USER->IsAuthorized()):?>
			<!-- Main sidebar -->
			<div class="sidebar sidebar-main">
				<div class="sidebar-content">

					<!-- User menu -->
					<?$APPLICATION->ShowViewContent("left_user")?>
					<!-- /user menu -->


					<!-- Main navigation -->
					<?$APPLICATION->IncludeComponent(
						"bitrix:menu",
						"calls_analytic_left_menu",
						Array(
							"ALLOW_MULTI_SELECT" => "N",
							"CHILD_MENU_TYPE" => "left",
							"DELAY" => "N",
							"MAX_LEVEL" => "2",
							"MENU_CACHE_GET_VARS" => array(0=>"",),
							"MENU_CACHE_TIME" => "3600",
							"MENU_CACHE_TYPE" => "N",
							"MENU_CACHE_USE_GROUPS" => "Y",
							"ROOT_MENU_TYPE" => "left",
							"USE_EXT" => "N"
						)
					);?>
					<!-- /main navigation -->

				</div>
			</div>
			<!-- /main sidebar -->
			<?endif;?>

			<!-- Main content -->
			<div class="content-wrapper">


				<!-- Content area -->
				<div class="content">
