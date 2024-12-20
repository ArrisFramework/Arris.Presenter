{*{$size|sf_array:[decimals => 3, decimal_separator => ',', thousands_separator => '-', units => ['байт', 'кил', 'мегов', 'гигов', 'теров', 'PB', 'EB', 'ZB', 'YB']]}*}
{*


{$size|sf_list: : ',' : '-' : ['байт', 'кил', 'мегов', 'гигов', 'теров', 'PB', 'EB', 'ZB', 'YB'] }

{$size|sf_array:[decimal_separator => '|']}

*}
{assign var="var" value=15}
{$var} [{$var|pluralForm:['строка','строки','строк']}]
