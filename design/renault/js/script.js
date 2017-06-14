/*count block with questions and add numbers pagination*/
(function( $ ) {
   
  

})( jQuery );
$(document).ready(function () {
    var version = detectIE();

    setTimeout(function () {
        $('.map_wrap').addClass('hide').removeClass('absolute');
    },1000);

   $('label.phone input').mask('+00(000) 000-00-00', {placeholder: "З кодом міста, наприклад, +38(050) 123-45-67"});

    Noetic.load();


    $('select[name="Model"]').focus(function () {
            $('.select_wrap').addClass('is_focused');
    });
    $('select[name="Model"]').blur(function () {
            $('.select_wrap').removeClass('is_focused');
    });

    $('select[name="Model"]').change(function(){
        var selectedItem = $(this).find('option:selected').val();
        //console.log(selectedItem);
        if( selectedItem != '' ){
            $('.select_wrap').addClass('chosed');
        }
    });

    var $dates = $(".date input").datepicker({
        minDate: 0,
        inline: true,
        changeYear: true,
        changeMonth: true,
        beforeShow:function(textbox, instance){
            $('#ui-datepicker-div').css({
                position: 'absolute',
                top:-20,
                left:5
            });
            $('#date_wrap').append($('#ui-datepicker-div'));
            $('#ui-datepicker-div').hide();
        },
        onSelect: function(dateText, inst) {
            $('#clear_dates').show().on('click', function () {
                $dates.datepicker('setDate', null);
                $(this).hide();
            });
        }
    });

    /*share friend show social menu*/
    $('.share').on('click', function(){
        $('.share42init').toggleClass("show");
    });

    $('#btn_dealer').on('click', function(){
        $('.map_wrap').toggleClass('hide');
    });
    /*mobile menu*/
    $('.toggle_menu').on('click', function(){
        $('.header_menu').addClass('show_menu');
    });
    $('.btn_close').on('click', function(){
        $('.header_menu').removeClass('show_menu');
    });

    /*navigation between block of questions*/
    (function( $ ) {
        /*count current block with questions
        var currentBlock = 0;

        $(".next").on("click", function() {
            currentBlock = navigationBlock(currentBlock, "next");
        });

        $(".prev").on("click", function() {
            currentBlock = navigationBlock(currentBlock, "prev");
        });

        function removeClassActive(num) {
            var blockQuestion = $(".test__wrap_question"),
                paginats = $(".pagination");

            blockQuestion.eq(num).removeClass("active_block");
            paginats.eq(num).removeClass("current_block");
        }

        function addClassActive(num) {
            var blockQuestion = $(".test__wrap_question"),
                paginats = $(".pagination");

            blockQuestion.eq(num).addClass("active_block");
            paginats.eq(num).addClass("current_block");
        }

        function blockNextButton(num) {
            var nextBlock = $(".test__wrap_question").eq(num),
                radioButton = nextBlock.find("input[type=radio]");

            if(!$(radioButton).is(":checked")) {
                $('.next').attr("disabled", "disabled");
                return ;
            }

            $(".next").removeAttr("disabled");
            $(".send_result").removeAttr("disabled");
        }

        function blockPrevButton(num) {
            if( num === 0 ) {
                $('.prev').removeClass("active_block");
                return ;
            }

            $('.prev').addClass("active_block");
        }

        function unblockButtonSend(num) {
            var numBlock = $(".test__wrap_question").length;
            if(num === numBlock-1) {
                $(".next").removeClass("active_block");
                $(".send_result").addClass("active_block")
                    .attr("disabled","disabled");
                return ;
            }

            $(".next").addClass("active_block");
            $(".send_result").removeClass("active_block");
        }

        function navigationBlock(num, direction) {
            removeClassActive(num);
            if(direction === "next") {
                num++;
            } else {
                num--;
            }
            addClassActive(num);
            unblockButtonSend(num);
            blockNextButton(num);
            blockPrevButton(num);
            return num;
        }*/

    })( jQuery );


    /*open modal form registed*/

    (function( $ ) {
        $("a.show_fancy").fancybox({
            maxWidth	: '90%',
            maxHeight	: '100%',
            fitToView	: false,
            width		: '100%',
            height		: 'auto',
            autoSize	: false,
            closeClick	: false,
            openEffect	: 'none',
            closeEffect	: 'none',
            closeBtn    : false
        });

        $(".btn_close").on("click", function() {
            $.fancybox.close();
        });

    })( jQuery );


    /**
     * detect IE
     * returns version of IE or false, if browser is not Internet Explorer
     */

    function detectIE() {
        var ua = window.navigator.userAgent;
        var msie = ua.indexOf('MSIE ');
        if (msie > 0) {
            // IE 10 or older => return version number
            return parseInt(ua.substring(msie + 5, ua.indexOf('.', msie)), 10);
        }

        var trident = ua.indexOf('Trident/');
        if (trident > 0) {// IE 11 => return version number
            var rv = ua.indexOf('rv:');
            return parseInt(ua.substring(rv + 3, ua.indexOf('.', rv)), 10);
        }

        var edge = ua.indexOf('Edge/');
        if (edge > 0) {// Edge (IE 12+) => return version number
            return parseInt(ua.substring(edge + 5, ua.indexOf('.', edge)), 10);
        }// other browser
        return false;
    }


    var version = detectIE();
    if (version === false) {


    }else if(version == 10){
        $('body').empty();
        $('body').append(
            ' <div class="interbet_block">' +
            '<div class="ie_bg" style="background-image:url(http://'+ location.host +'/design/renault/img/ie10.png">'+
            '<div class="window_block">'+
            '<div class="message_wrapper">'+
            '<div class="bottom_block">'+
            '<p class="bold">Ви використовуєте застарілу версію  Internet Explorer</p>'+
            '<p class="next_p">Щоб отримати можливість ознайомитися з сайтом, поновіть Ваш браузер</p>'+
            '<a href="http://browsehappy.com/" rel="nofollow">Оновити браузер</a>'+
            '</div>'+
            '</div>'+
            '</div>'+
            '</div></div>'
        )
    } else if(version > 10){}



});


jQuery(function($){
    $.datepicker.regional['ua'] = {
        closeText: 'Закрити',
        prevText: '&#x3c;Попер',
        nextText: 'Наст&#x3e;',
        currentText: 'Сьогодні',
        monthNames: ['Січень','Лютий','Березень','Квітень','Травень','Червень',
            'Липень','Серпень','Вересень','Жовтень','Листопад','Грудень'],
        monthNamesShort: ['Січ','Лют','Бер','Квіт','Трав','Черв',
            'Лип','Серп','Вер','Жовт','Лист','Груд'],
        dayNames: ['неділя', 'понеділок', 'вівторок', 'середа', 'четвер', 'пятниця', 'субота'],
        dayNamesShort: ['нед','пнд','втр','срд','чтв','птн','сбт'],
        dayNamesMin: ['Нд','Пн','Вт','Ср','Чт','Пт','Сб'],
        weekHeader: 'Не',
        dateFormat: 'dd.mm.yy',
        firstDay: 1,
        isRTL: false,
        showMonthAfterYear: false,
        yearSuffix: ''};
    $.datepicker.setDefaults($.datepicker.regional['ua']);
});

var monthLocal = ['січня', 'лютого', 'березня', 'квітня', 'травня', 'червня', 'липня', 'серпня', 'вересня', 'жовтня', 'листопада', 'грудня'];