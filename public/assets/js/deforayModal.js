function pageWidth() {
    return window.innerWidth != null ? window.innerWidth : document.documentElement && document.documentElement.clientWidth ? document.documentElement.clientWidth : document.body != null ? document.body.clientWidth : null;
}
function pageHeight() {
    return window.innerHeight != null ? window.innerHeight : document.documentElement && document.documentElement.clientHeight ? document.documentElement.clientHeight : document.body != null ? document.body.clientHeight : null;
}
function posLeft() {
    return typeof window.pageXOffset != 'undefined' ? window.pageXOffset : document.documentElement && document.documentElement.scrollLeft ? document.documentElement.scrollLeft : document.body.scrollLeft ? document.body.scrollLeft : 0;
}
function posTop() {
    return typeof window.pageYOffset != 'undefined' ? window.pageYOffset : document.documentElement && document.documentElement.scrollTop ? document.documentElement.scrollTop : document.body.scrollTop ? document.body.scrollTop : 0;
}
function getEl(x) {
    return document.getElementById(x);
}

var timer = null;
function goingDown(elm, toTop) {
    var top = parseInt(elm.offsetTop);
    var change = toTop - top;
    if (change <= 0) {
        clearTimeout(timer);
        return;
    }
    var total = top + Math.ceil((change / 12));
    elm.style.top = total + 'px';
    function c() {
        goingDown(elm, toTop);
    }
    timer = setTimeout(c, 10);
}



function goingUp(elm, toTop) {
    var top = parseInt(elm.offsetTop);
    var change = top - toTop;
    if (change <= 0) {
        clearTimeout(timer);
        return;
    }
    var total = top - Math.ceil((change / 12));
    elm.style.top = total + 'px';
    function c() {
        goingUp(elm, toTop);
    }
    timer = setTimeout(c, 10);
}


function scrollFix() {
    var obbx = getEl('mbox');
    var olay = getEl('ol');
    if (obbx.style.display != 'none') {
        clearTimeout(timer);
        var tp = posTop() + ((pageHeight() - hit) / 2) - 12;
        var lt = posLeft() + ((pageWidth() - wid) / 2) - 12;
        tp = (tp < 0 ? 0 : tp);
        if (obbx.offsetTop < tp) {
            goingDown(obbx, tp);
        }
        else {
            goingUp(obbx, tp);
        }
        obbx.style.left = (lt < 0 ? 0 : lt) + 'px';
    }
}
function sizeFix() {
    var obol = getEl('ol');
    obol.style.height = pageHeight() + 'px';
    obol.style.width = pageWidth() + 'px';
}
function kp(e) {
    ky = e ? e.which : event.keyCode;
    if (ky == 88 || ky == 120) hidedefModal();
    return false
}

var wid = 0;
var hit = 0;

function showdefModal(obl, wd, ht) {
    if (!document.getElementById('ol') || !document.getElementById('mbd')) {
        initmb();
    }

    var obol = document.getElementById('ol');
    var obbxd = document.getElementById('mbd');
    obbxd.innerHTML = document.getElementById(obl).innerHTML;

    // Make the overlay cover the entire viewport and stay fixed when scrolling
    obol.style.position = 'fixed';
    obol.style.top = '0';
    obol.style.bottom = '0';
    obol.style.left = '0';
    obol.style.right = '0';
    obol.style.display = 'block';

    var obbx = document.getElementById('mbox');
    obbx.style.display = 'block';
    obbx.style.width = wd + 'px';
    obbx.style.height = ht + 'px';

    // Center the modal using CSS
    obbx.style.position = 'fixed';
    obbx.style.top = '50%';
    obbx.style.left = '50%';
    obbx.style.transform = 'translate(-50%, -50%)';

    // Disable scrolling on the body
    document.body.style.overflow = 'hidden';
}


function hidedefModal() {
    document.getElementById('ol').style.display = 'none';
    document.getElementById('mbox').style.display = 'none';

    // Re-enable scrolling on the body
    document.body.style.overflow = '';
}

function initmb() {

    if (jQuery("#ol").length > 0 && jQuery("#mbox").length > 0) {
        return false;
    }
    var ab = 'absolute';
    var n = 'none';
    var obody = document.getElementsByTagName('body')[0];
    var frag = document.createDocumentFragment();
    var obol = document.createElement('div');
    obol.setAttribute('id', 'ol');
    obol.style.display = n;
    obol.style.position = ab;
    obol.style.top = 0;
    obol.style.left = 0;
    obol.style.zIndex = 9998;
    obol.style.width = '100%';
    obol.style.height = jQuery(document).height();
    //obol.setAttribute('onclick', 'hidedefModal();');
    frag.appendChild(obol);
    var obbx = document.createElement('div');
    obbx.setAttribute('id', 'mbox');
    obbx.style.display = n;
    obbx.style.position = ab;
    obbx.style.zIndex = 999;
    var obl = document.createElement('span');
    obbx.appendChild(obl);
    var obbxd = document.createElement('div');
    obbxd.setAttribute('id', 'mbd');
    obl.appendChild(obbxd);
    frag.insertBefore(obbx, obol.nextSibling);
    obody.insertBefore(frag, obody.firstChild);

    window.onscroll = scrollFix;
    window.onresize = sizeFix;
}
window.onload = initmb;
