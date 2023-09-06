import jQuery from 'jquery';

const Selectors = {
    timedue: "#id_timedue_enabled",
    timeclose: "#id_timeclose_enabled",
    timedueselector: "select[id^=id_timedue_]",
};

export const init = ({lateperiod, holiday}) => {
    let timedue = jQuery(Selectors.timedue);
    timedue.click(function() {
        if(timedue.is(':checked')) {
            // Enable timeclose when timedue is enabled.
            if (!timeclose.is(':checked')) {
                timeclose.trigger('click');
            }
            calculate_close_date(lateperiod, holiday);
        }
    });

    let timeclose = jQuery(Selectors.timeclose);
    timeclose.click(function() {
        // Disable timedue when timeclose is disabled.
        if(!timeclose.is(':checked') && timedue.is(':checked') ) {
            // Timedue is unchecked, this ensures that the timeclose is not triggered 'click'. Hence dose not cause infinite loop.
            timedue.trigger('click');
        }
    });

    let timedueselector = jQuery(Selectors.timedueselector);
    timedueselector.on('change', function() {
        calculate_close_date(lateperiod, holiday);
    });
};

export const calculate_close_date = (lateperiod, holiday) => {
    // The lateperiod and holiday are unit timestamp, in seconds.
    // Javascript timestamp value is milliseconds.
    let timeduevalue = get_date('timedue');
    let timeduedate = new Date(
            timeduevalue.year,
            timeduevalue.month - 1, // The month selector start from 1 instead of 0.
            timeduevalue.day,
            timeduevalue.hour,
            timeduevalue.minute,
            0);
    let timeduetimestamp = timeduedate.getTime();
    let timeclosetimestamp = timeduetimestamp + (lateperiod * 1000);

    // Skips weekends.
    let temptimestamp = timeduetimestamp;
    while (temptimestamp < timeclosetimestamp) {
        temptimestamp += 86400000;
        let day = new Date(temptimestamp).getDay();
        if (day == 6 || day == 0) {
            timeclosetimestamp += 86400000;
        }
    }

    // Skip holidays.
    let holidaydates = holiday.split(',');
    holidaydates.forEach(element => {
        element = element * 1000;
        if (element >= timeduetimestamp && element < timeclosetimestamp) {
            timeclosetimestamp += 86400000;
        }
    });

    let timeclosedate = new Date(timeclosetimestamp);
    set_date('timeclose', timeclosedate);
};

export const get_date = (elementName) => {
    let value = {
        'day': jQuery('[name="' + elementName + '[day]"]').val(),
        'month': jQuery('[name="' + elementName + '[month]"]').val(),
        'year': jQuery('[name="' + elementName + '[year]"]').val(),
        'hour': jQuery('[name="' + elementName + '[hour]"]').val(),
        'minute': jQuery('[name="' + elementName + '[minute]"]').val(),
     };
    return value;
};

export const set_date = (elementName, newvalue) => {
    jQuery('[name="' + elementName + '[day]"]').val(newvalue.getDate());
    jQuery('[name="' + elementName + '[month]"]').val(newvalue.getMonth() + 1);
    jQuery('[name="' + elementName + '[year]"]').val(newvalue.getFullYear());
    jQuery('[name="' + elementName + '[hour]"]').val(newvalue.getHours());
    jQuery('[name="' + elementName + '[minute]"]').val(newvalue.getMinutes());
};
