<?php
IncludeModuleLangFile(__FILE__);
?>
<form action="<?= $APPLICATION->GetCurPage()?>">
	<?=bitrix_sessid_post()?>
	<input type="hidden" name="lang" value="<?echo LANG?>">
	<input type="hidden" name="id" value="zr.paidaccess">
	<input type="hidden" name="uninstall" value="Y">
	<input type="hidden" name="step" value="2">
	<?echo CAdminMessage::ShowMessage(GetMessage("MOD_UNINST_WARN"))?>
	<p><?echo GetMessage("MOD_UNINST_SAVE")?></p>
	<p><input type="checkbox" name="savedata" id="savedata" value="Y" checked><label for="savedata"><?echo GetMessage("MOD_UNINST_SAVE_TABLES")?></label></p>
	<p><?echo GetMessage("MOD_UNINST_SAVE_MAIL_HINT")?></p>
	<p><input type="checkbox" name="savemail" id="savemail" value="Y" checked><label for="savemail"><?echo GetMessage("MOD_UNINST_SAVE_MAIL")?></label></p>
	<input type="submit" name="inst" value="<?echo GetMessage("MOD_UNINST_DEL")?>">
</form>