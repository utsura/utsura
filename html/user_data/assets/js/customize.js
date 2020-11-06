/* カスタマイズ用Javascript */
$(function(){
    $("[id=js-nav-open]").hover(function(){
        $(this).children('.ec-itemNavBrand__children').css("display", "flex");
        $(this).children('.ec-itemNavBrand__children').stop().slideDown(300);
        $(this).children('.ec-itemNavBrand__anchor').css("background-color", "#f5f5f5");
    }, function() {
        $(this).children('.ec-itemNavBrand__children').stop().slideUp('fast');
        $(this).children('.ec-itemNavBrand__anchor').css("background-color", "#ffffff");
    });
    $("img.lazyload").lazyload();
});

$(function() {
    var $header = $('#top-head');
    $(window).scroll(function() {
        if ($(window).scrollTop() > 50) {
            $header.addClass('fixed');
        } else {
            $header.removeClass('fixed');
        }
    });
});

$(function() {
    var staticfixedElement = $("#top-head").offset().top;
    $(window).on("scroll", function() {
        var scroll = $(window).scrollTop + $(window).height;
        if(scroll >= staticfixedElement){
            alert("同じ");
        }
    });
})
