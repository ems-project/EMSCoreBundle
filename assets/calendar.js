'use strict';

import './js/core';
import './css/app.scss';
import './css/app.less';


import EmsListeners from './js/EmsListeners';
window.EmsListeners = EmsListeners;

new EmsListeners(document);



function updateEvent(event){
    const data = {
        start :event.start.format(),
        ouuid :event.id,
    };

    if(event.end) {
        data.end = event.end.format();
    }
    window.ajaxRequest.post(divCalendar.data('replan-calendar-url'), data);
}


const divCalendar =$('#calendar');


$(function () {

    /* initialize the external events
     -----------------------------------------------------------------*/
    function ini_events(ele) {
        ele.each(function () {

            // create an Event Object (http://arshaw.com/fullcalendar/docs/event_data/Event_Object/)
            // it doesn't need to have a start or end
            const eventObject = {
                title: $.trim($(this).text()) // use the element's text as the event title
            };

            // store the Event Object in the DOM element so we can get to it later
            $(this).data('eventObject', eventObject);

            // make the event draggable using jQuery UI
            $(this).draggable({
                zIndex: 1070,
                revert: true, // will cause the event to go back to its
                revertDuration: 0  //  original position after the drag
            });

        });
    }

    ini_events($('#external-events div.external-event'));

    /* initialize the calendar
     -----------------------------------------------------------------*/

    const calendar = divCalendar.fullCalendar({
        header: {
            left: 'prev,next today',
            center: 'title',
            right: 'month,agendaWeek,agendaDay'
        },
        buttonText: {
            today: 'today',
            month: 'month',
            week: 'week',
            day: 'day'
        },
        //Random default events
        events:  function(from, to, timezone, callback){
            let data = $("form[name=search_form]").serialize();
            data = data+'&from='+from.format()+'&to='+to.format();

            window.ajaxRequest.get(divCalendar.data('search-calendar-url'), data)
                .success(function(response) {
                    callback(response.events);
                });
        },
        eventDrop: updateEvent,
        eventResize: updateEvent,
        editable: true,
        firstDay: divCalendar.data('calendar-first-day'),
        slotDuration: divCalendar.data('calendar-slot-duration'),
        weekends: !!divCalendar.data('calendar-weekends'),
        locale: divCalendar.data('calendar-locale'),
        timeFormat: divCalendar.data('calendar-time-format')
    });

    $('#search_form_applyFilters').click(function (e) {
        e.preventDefault();
        calendar.fullCalendar( 'refetchEvents' );
    });

    /* ADDING EVENTS */
    let currColor = "#3c8dbc"; //Red by default
    //Color chooser button
    // var colorChooser = $("#color-chooser-btn");
    $("#color-chooser > li > a").click(function (e) {
        e.preventDefault();
        //Save color
        currColor = $(this).css("color");
        //Add color effect to button
        $('#add-new-event').css({"background-color": currColor, "border-color": currColor});
    });
    $("#add-new-event").click(function (e) {
        e.preventDefault();
        const newEvent = $("#new-event");
        //Get value and make sure it is not null
        const val = newEvent.val();
        if (val.length === 0) {
            return;
        }

        //Create events
        const event = $("<div />");
        event.css({"background-color": currColor, "border-color": currColor, "color": "#fff"}).addClass("external-event");
        event.html(val);
        $('#external-events').prepend(event);

        //Add draggable funtionality
        ini_events(event);

        //Remove event from text input
        newEvent.val("");
    });
});