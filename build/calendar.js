(()=>{"use strict";var a={833:()=>{jQuery(document).ready((function(a){var e=a("#loading").hide();a(document).ajaxStart((function(){e.show()})).ajaxStop((function(){e.hide()})),a("div.rrze-calendar").on("click",".calendar-pager a",(function(e){e.preventDefault();var r=a("div.calendar-wrapper"),t=r.data("period"),n=r.data("layout"),o=a(this).data("direction"),d=a(this).data("taxquery");a.post(rrze_calendar_ajax.ajax_url,{_ajax_nonce:rrze_calendar_ajax.nonce,action:"rrze-calendar-update-calendar",period:t,layout:n,direction:o,taxquery:d},(function(e){r.remove(),a("div.rrze-calendar").append(e)}))}))}))}},e={};function r(t){var n=e[t];if(void 0!==n)return n.exports;var o=e[t]={exports:{}};return a[t](o,o.exports,r),o.exports}r.n=a=>{var e=a&&a.__esModule?()=>a.default:()=>a;return r.d(e,{a:e}),e},r.d=(a,e)=>{for(var t in e)r.o(e,t)&&!r.o(a,t)&&Object.defineProperty(a,t,{enumerable:!0,get:e[t]})},r.o=(a,e)=>Object.prototype.hasOwnProperty.call(a,e),r(833)})();