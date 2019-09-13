$(function () {
    $.nette.ext('init').linkSelector = 'a.ajax, .paginator a';
    $.nette.ext('unique',false);
    $.nette.init();
});
