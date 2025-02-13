/*!
 * Bootstrap-select v1.13.5 (https://developer.snapappointments.com/bootstrap-select)
 *
 * Copyright 2012-2018 SnapAppointments, LLC
 * Licensed under MIT (https://github.com/snapappointments/bootstrap-select/blob/master/LICENSE)
 */

(function (root, factory) {
    if (root === undefined && window !== undefined) root = window;
    if (typeof define === 'function' && define.amd) {
        // AMD. Register as an anonymous module unless amdModuleId is set
        define(["jquery"], function (a0) {
            return (factory(a0));
        });
    } else if (typeof module === 'object' && module.exports) {
        // Node. Does not work with strict CommonJS, but
        // only CommonJS-like environments that support module.exports,
        // like Node.
        module.exports = factory(require("jquery"));
    } else {
        factory(root["jQuery"]);
    }
}(this, function (jQuery) {

    (function ($) {
        $.fn.selectpicker.defaults = {
            noneSelectedText: 'Nic není vybráno',
            noneResultsText: 'Žádné výsledky {0}',
            countSelectedText: 'Označeno {0} z {1}',
            maxOptionsText: ['Limit překročen ({n} {var} max)', 'Limit skupiny překročen ({n} {var} max)', ['položek', 'položka']],
            selectAllText: 'Vše',
            deselectAllText: 'Vše',
            multipleSeparator: ', ',
            selectedTextFormat: 'count > 3',
            actionsBox: true,
            iconBase: 'fa',
            tickIcon: 'fa-check'
        };
    })(jQuery);


}));
