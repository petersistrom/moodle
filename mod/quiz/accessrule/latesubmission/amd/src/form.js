const Selectors = {
    timedue: "#id_timedue_enabled",
    timeclose: "#id_timeclose_enabled",
    timedueselector: "select[id^=id_timedue_]",
};

export const init = ({lateperiod, holiday}) => {
    let timedue = document.querySelector(Selectors.timedue);
    timedue.addEventListener("click", function() {
        if (timedue.checked) {
            // Enable timeclose when timedue is enabled.
            if (!timeclose.checked) {
                timeclose.click();
            }
            calculateCloseDate(lateperiod, holiday);
        }
    });

    let timeclose = document.querySelector(Selectors.timeclose);
    timeclose.addEventListener("click", function() {
        // Disable timedue when timeclose is disabled.
        if (!timeclose.checked && timedue.checked) {
            // Timedue is unchecked, this ensures that the timeclose is not triggered 'click'. Hence dose not cause infinite loop.
            timedue.click();
        }
    });

    let timedueselector = document.querySelector(Selectors.timedueselector);
    timedueselector.addEventListener("change", function() {
        calculateCloseDate(lateperiod, holiday);
    });
};

export const calculateCloseDate = (lateperiod, holiday) => {
    // The lateperiod and holiday are unit timestamp, in seconds.
    // Javascript timestamp value is milliseconds.
    const DAYSECS = 86400000;
    let timeduevalue = getDate('timedue');
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
            timeclosetimestamp += DAYSECS;
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
    setDate('timeclose', timeclosedate);
};

export const getDate = (elementName) => {
    let value = {
        'day': document.querySelector('[name="' + elementName + '[day]"]').value,
        'month': document.querySelector('[name="' + elementName + '[month]"]').value,
        'year': document.querySelector('[name="' + elementName + '[year]"]').value,
        'hour': document.querySelector('[name="' + elementName + '[hour]"]').value,
        'minute': document.querySelector('[name="' + elementName + '[minute]"]').value,
     };
    return value;
};

export const setDate = (elementName, newvalue) => {
    document.querySelector('[name="' + elementName + '[day]"]').value = newvalue.getDate();
    document.querySelector('[name="' + elementName + '[month]"]').value = newvalue.getMonth() + 1;
    document.querySelector('[name="' + elementName + '[year]"]').value = newvalue.getFullYear();
    document.querySelector('[name="' + elementName + '[hour]"]').value = newvalue.getHours();
    document.querySelector('[name="' + elementName + '[minute]"]').value = newvalue.getMinutes();
};
