(function(){

  var elem = document.getElementById('my-email-log');
  var iframe = elem.querySelector('iframe');

  if (! iframe) {
    return;
  }

  var updateiFrame = function() {
    iframe.style.height = iframe.contentWindow.document.body.scrollHeight + 'px';
  };

  iframe.contentWindow.onload = function(){
    updateiFrame();
  };

  updateiFrame();

})();
