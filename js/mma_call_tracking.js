// Copyright (c) 2014 Message Metric
!function (document, undefined) {

	var qparam=function(e){e=e.replace(/[\[]/,"\\[").replace(/[\]]/,"\\]");var t=new RegExp("[\\?&]"+e+"=([^&#]*)"),n=t.exec(location.search);return n===null?"":decodeURIComponent(n[1].replace(/\+/g," "))}
	// Copyright (c) 2012 Florian H., https://github.com/florian/cookie.js
	!function(e,t){var n=function(){return n.get.apply(n,arguments)},r=n.utils={isArray:Array.isArray||function(e){return Object.prototype.toString.call(e)==="[object Array]"},isPlainObject:function(e){return!!e&&Object.prototype.toString.call(e)==="[object Object]"},toArray:function(e){return Array.prototype.slice.call(e)},getKeys:Object.keys||function(e){var t=[],n="";for(n in e)e.hasOwnProperty(n)&&t.push(n);return t},escape:function(e){return String(e).replace(/[,;"\\=\s%]/g,function(e){return encodeURIComponent(e)})},retrieve:function(e,t){return e==null?t:e}};n.defaults={},n.expiresMultiplier=86400,n.set=function(n,i,s){if(r.isPlainObject(n))for(var o in n)n.hasOwnProperty(o)&&this.set(o,n[o],i);else{s=r.isPlainObject(s)?s:{expires:s};var u=s.expires!==t?s.expires:this.defaults.expires||"",a=typeof u;a==="string"&&u!==""?u=new Date(u):a==="number"&&(u=new Date(+(new Date)+1e3*this.expiresMultiplier*u)),u!==""&&"toGMTString"in u&&(u=";expires="+u.toGMTString());var f=s.path||this.defaults.path;f=f?";path="+f:"";var l=s.domain||this.defaults.domain;l=l?";domain="+l:"";var c=s.secure||this.defaults.secure?";secure":"";e.cookie=r.escape(n)+"="+r.escape(i)+u+f+l+c}return this},n.remove=function(e){e=r.isArray(e)?e:r.toArray(arguments);for(var t=0,n=e.length;t<n;t++)this.set(e[t],"",-1);return this},n.empty=function(){return this.remove(r.getKeys(this.all()))},n.get=function(e,n){n=n||t;var i=this.all();if(r.isArray(e)){var s={};for(var o=0,u=e.length;o<u;o++){var a=e[o];s[a]=r.retrieve(i[a],n)}return s}return r.retrieve(i[e],n)},n.all=function(){if(e.cookie==="")return{};var t=e.cookie.split("; "),n={};for(var r=0,i=t.length;r<i;r++){var s=t[r].split("=");n[decodeURIComponent(s[0])]=decodeURIComponent(s[1])}return n},n.enabled=function(){if(navigator.cookieEnabled)return!0;var e=n.set("_","_").get("_")==="_";return n.remove("_"),e},typeof define=="function"&&define.amd?define(function(){return n}):typeof exports!="undefined"?exports.cookie=n:window.cookie=n}(document);
	// https://github.com/larryosborn/JSONP
	(function(){var e,n,r,t,o,d,u,i;r=function(e){return window.document.createElement(e)},t=window.encodeURIComponent,u=Math.random,e=function(e){var t,d,u,a,c;if(e=e?e:{},a={data:e.data||{},error:e.error||o,success:e.success||o,beforeSend:e.beforeSend||o,complete:e.complete||o,url:e.url||""},0===a.url.length)throw new Error("MissingUrl");return d=!1,a.beforeSend({},a)!==!1?(t=a.data[e.callbackName||"callback"]="jsonp_"+i(15),window[t]=function(e){a.success(e,a),a.complete(e,a);try{return delete window[t]}catch(n){return void(window[t]=void 0)}},c=r("script"),c.src=n(a),c.async=!0,c.onerror=function(e){return a.error({url:c.src,event:e}),a.complete({url:c.src,event:e},a)},c.onload=c.onreadystatechange=function(){return d||this.readyState&&"loaded"!==this.readyState&&"complete"!==this.readyState?void 0:(d=!0,c.onload=c.onreadystatechange=null,c&&c.parentNode&&c.parentNode.removeChild(c),c=null)},u=u||window.document.getElementsByTagName("head")[0]||window.document.documentElement,u.insertBefore(c,u.firstChild)):void 0},o=function(){return void 0},n=function(e){var n;return n=e.url,n+=e.url.indexOf("?")<0?"?":"&",n+=d(e.data)},i=function(e){var n;for(n="";n.length<e;)n+=u().toString(36)[2];return n},d=function(e){var n,r,o;n=[];for(r in e)o=e[r],n.push(t(r)+"="+t(o));return n.join("&")},"undefined"!=typeof define&&null!==define&&define.amd?define(function(){return e}):"undefined"!=typeof module&&null!==module&&module.exports?module.exports=e:this.JSONP=e}).call(this);
	// ki.js v1.1.0 - 2014-02-12 - Copyright (c) 2014 Denis Ciccale (@tdecs) - MIT license
	!function(a,b,c,d){function e(c){b.push.apply(this,c&&c.nodeType?[c]:""+c===c?a.querySelectorAll(c):d)}$=function(b){return/^f/.test(typeof b)?/c/.test(a.readyState)?b():$(a).on("DOMContentLoaded",b):new e(b)},$[c]=e[c]={length:0,on:function(a,b){return this.each(function(c){c.addEventListener(a,b)})},off:function(a,b){return this.each(function(c){c.removeEventListener(a,b)})},each:function(a,c){return b.forEach.call(this,a,c),this},splice:b.splice}}(document,[],"prototype");
	var mob=function(){var check = false;(function(a){if(/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i.test(a)||/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(a.substr(0,4)))check = true})(navigator.userAgent||navigator.vendor||window.opera); return check;}

	var key = false, sel = [];
	var msgmetric = function(s, key, var1, var2){
		var1 = var1 ? var1.replace(/[^0-9A-Za-z_]+/, '') : '';
		var2 = var2 ? var2.replace(/[^0-9A-Za-z_]+/, '') : '';
		var vk = (var1 || var2) ? '-'+var1+':'+var2 : '';
		var num = cookie.get('msgmetric_num'+vk);
		var phone = cookie.get('msgmetric_phone'+vk);
		if (phone) {
			$(s).each(function(el){ el.innerHTML = mob() ? '<a href="tel:'+num+'">'+phone+'</a>' : phone; });
		} else if (key) {
			sel[sel.length] = s;

			if (typeof cookie.get('msgmetric_phone'+vk) === 'undefined') {
				JSONP({
					url: 'https://app.messagemetric.com',
					data: { msgmetric_worker: 'mma_phone', key: key, gclid: cookie.get('msgmetric_gclid', ''),
						term: cookie.get('msgmetric_term', ''), refurl: cookie.get('msgmetric_refurl', ''),
						var1: var1, var2: var2 },
					success: function(data){
						if (data.phone) {
							cookie.set('msgmetric_phone'+vk, data.phone);
							cookie.set('msgmetric_num'+vk, data.num);
							for (var i = 0; i < sel.length; i++) msgmetric(sel[i]);
						}
					}
				});
			}
		}
	};

	if (!cookie.get('msgmetric')) {
		cookie.set({ msgmetric: 1, msgmetric_gclid: qparam('gclid'), msgmetric_term: qparam('mma_term'), msgmetric_refurl: document.referrer });
	}

	window.msgmetric = msgmetric;
}(document);
