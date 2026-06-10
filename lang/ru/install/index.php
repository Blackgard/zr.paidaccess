<?php

$MESS['ZR_PAIDACCESS_MODULE_NAME'] = 'Zr | Модуль доступа к контенту';
$MESS['ZR_PAIDACCESS_MODULE_DESCRIPTION'] = 'Модуль для работы с доступом к контенту';
$MESS['ZR_PAIDACCESS_PARTNER_NAME'] = 'alexandr-zr.ru';
$MESS['ZR_PAIDACCESS_PARTNER_URI'] = 'http://alexandr-zr.ru';
$MESS['ZR_PAIDACCESS_DENIED'] = 'Доступ закрыт';
$MESS['ZR_PAIDACCESS_READ_ONLY'] = 'Только чтение';
$MESS['ZR_PAIDACCESS_FULL_ACCESS'] = 'Полный доступ';
$MESS['ZR_PAIDACCESS_FULL_ACCESS_SETTINGS'] = 'Полный доступ включая настройки';
$MESS["ZR_PAIDACCESS_INSTALL_ERROR_VERSION"] = "Версия главного модуля ниже 14. Не поддерживается технология D7, необходимая модулю. Пожалуйста обновите систему.";
$MESS["ZR_PAIDACCESS_INSTALL_TITLE"] = "Установка модуля";
$MESS["ZR_PAIDACCESS_UNINSTALL_TITLE"] = "Удаление модуля";
$MESS["ZR_PAIDACCESS_INSTALL_SUCCESS"] = "Модуль успешно установлен";
$MESS["ZR_PAIDACCESS_UNINSTALL_SUCCESS"] = "Модуль успешно удален";
$MESS["ZR_PAIDACCESS_INSTALL_FAILED"] = "Ошибка при установке модуля";
$MESS["ZR_PAIDACCESS_UNINSTALL_FAILED"] = "Ошибка при удалении модуля";
$MESS['ZR_PAIDACCESS_MAIL_EVENT_PAYMENT_PAID_NAME'] = 'Подтверждение оплаты подписки (zr.paidaccess)';
$MESS['ZR_PAIDACCESS_MAIL_EVENT_PAYMENT_PAID_DESC'] = '#EMAIL# — email получателя
#USER_ID# — ID пользователя
#USER_NAME# — имя пользователя
#PAYMENT_ID# — ID платежа в модуле
#ORDER_ID# — номер заказа для шлюза
#AMOUNT# — сумма
#CURRENCY# — валюта
#BILLING_PERIOD# — расчётный период (ключ)
#BILLING_PERIOD_LABEL# — период (текст)
#DESCRIPTION# — назначение платежа
#DATE_PAID# — дата оплаты
#FISCAL_RECEIPT_NOTE# — примечание о фискальном чеке от банка
#GATEWAY_CODE# — код шлюза';
$MESS['ZR_PAIDACCESS_MAIL_EVENT_PAYMENT_PAID_SUBJECT'] = 'Оплата подписки на #SITE_NAME# получена';
$MESS['ZR_PAIDACCESS_MAIL_EVENT_PAYMENT_PAID_BODY'] = 'Здравствуйте, #USER_NAME#!<br><br>
Ваш платёж за период <b>#BILLING_PERIOD_LABEL#</b> успешно получен.<br>
Сумма: <b>#AMOUNT# #CURRENCY#</b><br>
Номер платежа: #PAYMENT_ID#<br>
Дата: #DATE_PAID#<br><br>
#FISCAL_RECEIPT_NOTE#<br><br>
С уважением,<br>#SITE_NAME#';
$MESS['ZR_PAIDACCESS_MAIL_EVENT_PAYMENT_FAILED_NAME'] = 'Ошибка оплаты подписки (zr.paidaccess)';
$MESS['ZR_PAIDACCESS_MAIL_EVENT_PAYMENT_FAILED_DESC'] = '#EMAIL# — email получателя
#USER_ID# — ID пользователя
#USER_NAME# — имя
#PAYMENT_ID# — ID платежа
#ORDER_ID# — номер заказа
#AMOUNT# — сумма
#CURRENCY# — валюта
#BILLING_PERIOD_LABEL# — период
#FAIL_REASON# — причина ошибки';
$MESS['ZR_PAIDACCESS_MAIL_EVENT_PAYMENT_FAILED_SUBJECT'] = 'Не удалось создать платёж за подписку на #SITE_NAME#';
$MESS['ZR_PAIDACCESS_MAIL_EVENT_PAYMENT_FAILED_BODY'] = 'Здравствуйте, #USER_NAME#!<br><br>
Не удалось обработать платёж за период <b>#BILLING_PERIOD_LABEL#</b>.<br>
Сумма: <b>#AMOUNT# #CURRENCY#</b><br>
Причина: #FAIL_REASON#<br><br>
Попробуйте оплатить снова на сайте или обратитесь в поддержку.<br><br>
С уважением,<br>#SITE_NAME#';
$MESS['ZR_PAIDACCESS_MAIL_EVENT_SUBSCRIPTION_DEBT_NAME'] = 'Просрочка оплаты подписки (zr.paidaccess)';
$MESS['ZR_PAIDACCESS_MAIL_EVENT_SUBSCRIPTION_DEBT_DESC'] = '#EMAIL# — email
#USER_NAME# — имя
#BILLING_PERIOD_LABEL# — период
#DUE_DATE# — срок оплаты
#AMOUNT# — сумма взноса
#GRACE_DAYS# — льготные дни';
$MESS['ZR_PAIDACCESS_MAIL_EVENT_SUBSCRIPTION_DEBT_SUBJECT'] = 'Просрочена оплата подписки на #SITE_NAME#';
$MESS['ZR_PAIDACCESS_MAIL_EVENT_SUBSCRIPTION_DEBT_BODY'] = 'Здравствуйте, #USER_NAME#!<br><br>
Срок оплаты взноса за период <b>#BILLING_PERIOD_LABEL#</b> истёк (#DUE_DATE#).<br>
Сумма к оплате: <b>#AMOUNT# #CURRENCY#</b>.<br>
#GRACE_DAYS# льготных дней после срока уже учтены в политике доступа.<br><br>
Оплатите подписку на сайте, чтобы сохранить доступ.<br><br>
С уважением,<br>#SITE_NAME#';
$MESS['ZR_PAIDACCESS_MAIL_EVENT_SUBSCRIPTION_EXPIRING_NAME'] = 'Скоро окончание подписки (zr.paidaccess)';
$MESS['ZR_PAIDACCESS_MAIL_EVENT_SUBSCRIPTION_EXPIRING_DESC'] = '#EMAIL# — email
#USER_NAME# — имя
#PERIOD_END# — дата окончания доступа
#DAYS_LEFT# — дней до окончания
#AMOUNT# — сумма следующего взноса';
$MESS['ZR_PAIDACCESS_MAIL_EVENT_SUBSCRIPTION_EXPIRING_SUBJECT'] = 'Скоро окончание оплаченного периода подписки на #SITE_NAME#';
$MESS['ZR_PAIDACCESS_MAIL_EVENT_SUBSCRIPTION_EXPIRING_BODY'] = 'Здравствуйте, #USER_NAME#!<br><br>
Оплаченный период подписки заканчивается <b>#PERIOD_END#</b> (осталось #DAYS_LEFT# дн.).<br>
Сумма следующего взноса: <b>#AMOUNT# #CURRENCY#</b>.<br><br>
Заранее оплатите подписку на сайте, чтобы не прерывать доступ.<br><br>
С уважением,<br>#SITE_NAME#';
$MESS['ZR_PAIDACCESS_MAIL_EVENT_ADMIN_ERROR_NAME'] = 'Ошибка модуля платёжного доступа (zr.paidaccess)';
$MESS['ZR_PAIDACCESS_MAIL_EVENT_ADMIN_ERROR_DESC'] = '#EMAIL# — email администратора
#SITE_NAME# — название сайта
#ERROR_CODE# — код
#ERROR_MESSAGE# — текст
#CONTEXT# — контекст JSON
#PAYMENT_ID# — ID платежа
#USER_ID# — ID пользователя
#DATE# — дата';
$MESS['ZR_PAIDACCESS_MAIL_EVENT_ADMIN_ERROR_SUBJECT'] = '[#SITE_NAME#] Ошибка zr.paidaccess: #ERROR_CODE#';
$MESS['ZR_PAIDACCESS_MAIL_EVENT_ADMIN_ERROR_BODY'] = 'Зафиксирована ошибка в модуле платёжного доступа.<br><br>
<b>Код:</b> #ERROR_CODE#<br>
<b>Сообщение:</b> #ERROR_MESSAGE#<br>
<b>Дата:</b> #DATE#<br>
<b>Платёж:</b> #PAYMENT_ID#<br>
<b>Пользователь:</b> #USER_ID#<br><br>
<b>Контекст:</b><br><pre>#CONTEXT#</pre><br>
Проверьте журнал в админке: Настройки → Платёжный доступ → Журнал.';
