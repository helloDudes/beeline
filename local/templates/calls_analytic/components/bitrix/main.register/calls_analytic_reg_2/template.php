<?
/**
 * Bitrix Framework
 * @package bitrix
 * @subpackage main
 * @copyright 2001-2014 Bitrix
 */

/**
 * Bitrix vars
 * @global CMain $APPLICATION
 * @global CUser $USER
 * @param array $arParams
 * @param array $arResult
 * @param CBitrixComponentTemplate $this
 */

if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)
	die();
?>
<!-- Registration form -->
<div id="modal-registration" class="modal fade">
	<div class="modal-dialog">
		<div class="modal-content login-form">

			<!-- Form -->
			<form id="reg-form" class="modal-body" method="post" action="/calls_analytic/" name="regform" enctype="multipart/form-data">
				<div class="text-center">
					<div class="icon-object" style="color: #FFFFFF"><i class="icon-plus3" style="color: #fbc91d"></i></div>
					<h5 class="content-group">Регистрация <small class="display-block">Все поля обязательны</small></h5>
				</div>
				<div class="content-divider form-group"><span>Ваши данные</span></div>
				<div class="form-group has-feedback has-feedback-left">
					<input size="30" type="text" autocomplete="off" name="REGISTER[LOGIN]" value="" class="form-control reg-login red-border" placeholder="Номер"/>
					<div class="form-control-feedback">
						<i class="icon-user-check text-muted"></i>
					</div>
				</div>
				<div class="form-group has-feedback has-feedback-left">
					<input size="30" type="password" name="REGISTER[PASSWORD]" value="" autocomplete="off" class="form-control reg-pass red-border" placeholder="Пароль" />
					<div class="form-control-feedback">
						<i class="icon-user-lock text-muted"></i>
					</div>
				</div>
				<div class="form-group has-feedback has-feedback-left">
					<input size="30" type="password" name="REGISTER[CONFIRM_PASSWORD]" value="" autocomplete="off"  class="form-control reg-confirm-pass red-border" placeholder="Повторите пароль" />
					<div class="form-control-feedback">
						<i class="icon-user-lock text-muted"></i>
					</div>
				</div>
				<div class="warning-message">

				</div>
				<div class="form-group submit-group">
					<button type="submit" name="register_submit_button" class="btn btn-block reg-submit" value="Регистрация">Регистрация <i class="icon-circle-right2 position-right"></i></button>
					<button type="button" class="btn btn-default btn-block" data-dismiss="modal">Отмена</button>
				</div>
				<span class="help-block text-center no-margin">Пароль должен быть не менее 6 символов длиной.</span>
				<!--<span class="help-block text-center no-margin">Продолжая, вы подтверждаете, что ознакомились с нашими <a href="#">условиями использования</a> и <a href="#">политикой использования cookie данных</a></span>-->
			</form>
			<!-- /form -->

		</div>
	</div>
</div>
<!-- /registration form -->
