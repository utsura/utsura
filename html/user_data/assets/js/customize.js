/* カスタマイズ用Javascript */

$("#js-nav").hover(function(){
    $(this).children('.ec-itemNavBrand__children').slideDown(300);
    $(this).children('.ec-itemNavBrand__anchor').css("background-color", "#f5f5f5");
    $(this).children('.ec-itemNavBrand__children').css("display", "flex");
}, function() {
    $(this).children('.ec-itemNavBrand__children').slideUp('fast');
    $(this).children('.ec-itemNavBrand__anchor').css("background-color", "#ffffff");
});
