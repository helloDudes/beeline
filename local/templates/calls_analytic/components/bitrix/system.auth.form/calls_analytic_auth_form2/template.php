<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>
<?if($arResult["ERROR_MESSAGE"]):?>
<script>
	alert("<?=str_replace("<br>", "", $arResult["ERROR_MESSAGE"]["MESSAGE"])?>");
</script>
<?endif;?>
<!-- Login form -->
<div id="modal-login" class="modal fade">
	<div class="modal-dialog">
		<div class="modal-content login-form">

			<!-- Form -->
			<form id="auth-form" class="modal-body" name="system_auth_form<?=$arResult["RND"]?>" method="post" target="_top" action="<?=$arResult["AUTH_URL"]?>">
				<?if($arResult["BACKURL"] <> ''):?>
					<input type="hidden" name="backurl" value="<?=$arResult["BACKURL"]?>" />
				<?endif?>
				<?foreach ($arResult["POST"] as $key => $value):?>
					<input type="hidden" name="<?=$key?>" value="<?=$value?>" />
				<?endforeach?>
				<input type="hidden" name="AUTH_FORM" value="Y" />
				<input type="hidden" name="TYPE" value="AUTH" />
				<div class="text-center">
					<div class="icon-object" style="color: #FFFFFF"><i class="icon-reading" style="color: #fbc91d"></i></div>
					<h5 class="content-group">Вход в ваш аккаунт <small class="display-block" style="background-color: none;">Ваши данные</small></h5>
				</div>

				<div class="form-group has-feedback has-feedback-left">
					<input type="text" class="form-control auth-login" placeholder="Телефон" name="USER_LOGIN" maxlength="50" value="" size="17" >
					<div class="form-control-feedback">
						<i class="icon-user text-muted"></i>
					</div>
				</div>

				<div class="form-group has-feedback has-feedback-left">
					<input type="password" class="form-control auth-password" placeholder="Пароль" name="USER_PASSWORD" maxlength="50" size="17" autocomplete="off">
					<div class="form-control-feedback">
						<i class="icon-lock2 text-muted"></i>
					</div>
				</div>
				<div class="warning-message"></div>
				<div class="form-group">
					<button type="submit" class="btn btn-block" name="Login" >Вход <i class="icon-arrow-right14 position-right"></i></button>
				</div>
			</form>
			<!-- /form -->

		</div>
	</div>
</div>
<!-- /login form -->
