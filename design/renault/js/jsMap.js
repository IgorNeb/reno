/* 
 * Maps Google
 */
var markersArray = [];
var markersID = [];
var markersMessages = [];
var infowindow   = new google.maps.InfoWindow();
var map, markerCluster;

var activeIcon = {
    url: '/design/renault/img/map/active-marker.png'
};

$(function() {
    if ($('#g_map').length > 0){
        var myMapLoc = new google.maps.LatLng(49.444684, 32.067596);

        var myOptions = {
            zoom: 5,
            scrollwheel: true,
            center: myMapLoc,
            disableDefaultUI: true,
            navigationControl: true,
            navigationControlOptions: {
                style: google.maps.NavigationControlStyle.ZOOM_PAN
            },

            mapTypeControl: true,
            mapTypeControlOptions: {
                style: google.maps.MapTypeControlStyle.DROPDOWN_MENU
            },
            streetViewControl: true,
            mapTypeId: google.maps.MapTypeId.ROADMAP,
            styles: [{"featureType":"administrative.locality","elementType":"all","stylers":[{"hue":"#2c2e33"},{"saturation":7},{"lightness":19},{"visibility":"on"}]},{"featureType":"landscape","elementType":"all","stylers":[{"hue":"#ffffff"},{"saturation":-100},{"lightness":100},{"visibility":"simplified"}]},{"featureType":"poi","elementType":"all","stylers":[{"hue":"#ffffff"},{"saturation":-100},{"lightness":100},{"visibility":"off"}]},{"featureType":"road","elementType":"geometry","stylers":[{"hue":"#bbc0c4"},{"saturation":-93},{"lightness":31},{"visibility":"simplified"}]},{"featureType":"road","elementType":"labels","stylers":[{"hue":"#bbc0c4"},{"saturation":-93},{"lightness":31},{"visibility":"on"}]},{"featureType":"road.arterial","elementType":"labels","stylers":[{"hue":"#bbc0c4"},{"saturation":-93},{"lightness":-2},{"visibility":"simplified"}]},{"featureType":"road.local","elementType":"geometry","stylers":[{"hue":"#e9ebed"},{"saturation":-90},{"lightness":-8},{"visibility":"simplified"}]},{"featureType":"transit","elementType":"all","stylers":[{"hue":"#e9ebed"},{"saturation":10},{"lightness":69},{"visibility":"on"}]},{"featureType":"water","elementType":"all","stylers":[{"hue":"#e9ebed"},{"saturation":-78},{"lightness":67},{"visibility":"simplified"}]}]
        };

        map = new google.maps.Map(document.getElementById("g_map"), myOptions);

    }
});
function initCluster(){

    var styleOption = [{   
        url: '/design/renault/img/map/m1.png',
         height: 54,
        width: 49,
        anchor: [-5, 0],
        fontFamily: "Condensed",
        textColor: '#ffffff',
        textSize: 22
      }, {
        url: '/design/renault/img/map/m1.png',
         height: 54,
        width: 49,
        anchor: [0, 0],
        fontFamily: "Condensed",
        textColor: '#ffffff',
        textSize: 22
      }, {
       url: '/design/renault/img/map/m1.png',
         height: 54,
        width: 49,
        anchor: [0, 0],
        fontFamily: "Condensed",
        textColor: '#ffffff',
        textSize: 22
      }];
    markerCluster = new MarkerClusterer(map, markersArray, {styles: styleOption, imagePath: '/design/renault/img/map/m'});
  //  console.log(markersArray);
}

function clearCluster(){
    markerCluster.clearMarkers();
    markersArray = [];
}
   
/**
* Наносит маркер магазина на карту
*/
function add_marker(id, lat, lng) { 
//    
//    var lat = parseFloat(latlngStr[0]);
//    var lng = parseFloat(latlngStr[1]);
    //var forclicklat = lat + 0.26;
    
    var location = new google.maps.LatLng(lat, lng);    
    var image = new google.maps.MarkerImage("/design/renault/img/map/marker.png");
    
    var marker = new google.maps.Marker({
        position: location,  
        map: map,
        icon:image,
         height: 54,
        width: 49
    });
    
    //var info_content = getMessage(id);//, name, addresStore, phones);
    attachMessage(marker, id);
  
    markersArray.push(marker);
    markersID[id] = markersArray.length - 1;
    
    shangeMarker(marker);
}

function shangeMarker(marker) {
    google.maps.event.addListener(marker, 'click', (function(marker) {
        
        return function() {
            //console.log(activeIcon);
            for (var j = 0; j < markersArray.length; j++) {
                markersArray[j].setIcon("/design/renault/img/map/marker.png");
            }
            this.setIcon(activeIcon);
        }
    })(marker));
}

//добавляем текст при клике
function attachMessage(marker, id) {
  
   // var mapshere = marker.get('map');
    google.maps.event.addListener(marker, 'click', function() {           
        //infowindow.open(mapshere, marker);        
        AjaxRequest.send('', '/'+langURL+'action/form/load_dealer/', '', true, {'dealer_id' : id});
        //map.setZoom(10);
        map.setCenter( marker.getPosition() );
    });
  
    
    
    google.maps.event.addListener(map, 'click', function() {    
        infowindow.close(); 
    });
}

function checkDealer(id, name) {
    $('#autocomplete_dealer').removeClass('is-active').html('');
    AjaxRequest.send('', '/'+langURL+'action/form/load_dealer/', '', true, {'dealer_id' : id});
    
    $('#map_dealer_input').val(name);
    $('#selected_dealer').html(name);
    
    $('.form_section.dealer').addClass('complete');
    $('.wrap_field input[type="text"]').val(name);
    
    var index = markersID[id];
    var marker = markersArray[index];
    map.setZoom(12);
    map.setCenter( marker.getPosition() );
    
    marker.setIcon(activeIcon);
}

function searchDealer(name) {
    AjaxRequest.send('', '/'+langURL+'action/form/search_dealer/', '', true, {'text' : name});
}

//создание инфо блока
function getMessage(id, name, address, phone) {
  
//  if (time.length > 0) {
//    var text_time   = $("#text_time").val();
//    time   = "<b>"+text_time+"</b><span class='shop_time'>"+time+"</span>";
//  }
  markersMessages[id] = "<div class='open_store_info'><span class='shop_title'>"+name+"</span><span class='shop_adress'>"+address+"</span><span class='shop_phone'>"+phone+"</span></div>";
  
  return markersMessages[id];  
}


function openMarkerInfo(id){
    
    var marker = markersArray[id];
    map.setZoom(12);
    map.setCenter( marker.getPosition() );
    
    var infowindow = new google.maps.InfoWindow({
      content: markersMessages[id]
    });
    
    infowindow.open(marker.get('map'), marker);
    
//    $('.stores_block').click(function(){
//        infowindow.close(); 
//    });
}

function chooseDealer(name){
    $('#map_dealer_result').html('');
    $('#map_dealer_input').val(name);
    $('#selected_dealer').html(name);
    $('.form_section.dealer').addClass('complete');
    $('.map_wrap').addClass('hide');
}


function changeMap() 
{
    clearMarkers();
    $('#form_maps').submit();
	return false;
}

// Sets the map on all markers in the array.
function setAllMap(map) {
  for (var i = 0; i < markersArray.length; i++) {
    markersArray[i].setMap(map);
  }
}

// Removes the markers from the map, but keeps them in the array.
function clearMarkers() {
   //setAllMap(null);
    markersArray.forEach(function(item, i, arr) {
        markersArray[i].setMap(null);
    });
}
