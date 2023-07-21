
function showdefModal(actualContentDiv, maxWidth, maxHeight) {

    if (!document.getElementById('modalOverlay') || !document.getElementById('modalBoxDiv')) {
        initModalBox();
    }

    let modalOverlay = document.getElementById('modalOverlay');
    let modalBoxDiv = document.getElementById('modalBoxDiv');
    modalBoxDiv.innerHTML = document.getElementById(actualContentDiv).innerHTML;

    modalOverlay.style.display = 'block';
    var modalBox = document.getElementById('modalBox');
    modalBox.style.width = maxWidth + 'px';
    modalBox.style.height = maxHeight + 'px';
    modalBox.style.display = 'block';
    document.body.style.overflow = 'hidden';
    return false;
}


function hidedefModal() {
    document.getElementById('modalOverlay').style.display = 'none';
    document.getElementById('modalBox').style.display = 'none';

    // Re-enable scrolling on the body
    document.body.style.overflow = '';
}
function initModalBox() {

    if (document.getElementById('modalOverlay') && document.getElementById('modalBox')) {
        return;
    }

    var obody = document.getElementsByTagName('body')[0];
    var frag = document.createDocumentFragment();
    var modalOverlay = document.createElement('div');
    modalOverlay.id = 'modalOverlay';
    modalOverlay.style.display = 'none';
    modalOverlay.style.position = 'fixed';
    modalOverlay.style.top = '0';
    modalOverlay.style.left = '0';
    modalOverlay.style.right = '0';
    modalOverlay.style.bottom = '0';
    modalOverlay.style.zIndex = '9998';
    modalOverlay.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
    frag.appendChild(modalOverlay);


    var modalBox = document.createElement('div');
    modalBox.id = 'modalBox';
    modalBox.style.display = 'none';
    modalBox.style.position = 'fixed';
    modalBox.style.top = '50%';
    modalBox.style.left = '50%';
    modalBox.style.transform = 'translate(-50%, -50%)';
    modalBox.style.zIndex = '9999';

    let modalBoxDiv = document.createElement('div');
    modalBoxDiv.id = 'modalBoxDiv';
    modalBox.appendChild(modalBoxDiv);

    frag.insertBefore(modalBox, modalOverlay.nextSibling);
    obody.insertBefore(frag, obody.firstChild);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            hidedefModal();
        }
    });

}
window.onload = initModalBox;
