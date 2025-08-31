// LillyChat basic enhancements
(function(){
  function autoScrollMessages() {
    var pane = document.querySelector('.content-body');
    if (pane) {
      pane.scrollTop = pane.scrollHeight;
    }
  }

  function focusComposer() {
    var ta = document.querySelector('.compose textarea');
    if (ta) ta.focus();
  }

  // Auto-resize textarea
  function attachAutosize() {
    var ta = document.querySelector('.compose textarea');
    if (!ta) return;
    var resize = function() {
      ta.style.height = 'auto';
      ta.style.height = (ta.scrollHeight) + 'px';
    };
    ta.addEventListener('input', resize);
    resize();
  }

  // Members modal
  function attachMembersModal() {
    var openBtn = document.getElementById('open-members');
    var closeBtn = document.getElementById('close-members');
    var overlay = document.getElementById('members-modal');
    if (!overlay || !openBtn) return;

    function open() {
      overlay.classList.remove('hidden');
      overlay.setAttribute('aria-hidden', 'false');
    }
    function close() {
      overlay.classList.add('hidden');
      overlay.setAttribute('aria-hidden', 'true');
    }

    openBtn.addEventListener('click', open);
    if (closeBtn) closeBtn.addEventListener('click', close);
    overlay.addEventListener('click', function(e){
      // Close when clicking outside the modal card
      var card = overlay.querySelector('.modal-card');
      if (!card) return;
      if (!card.contains(e.target)) {
        close();
      }
    });
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape') close();
    });
  }

  document.addEventListener('DOMContentLoaded', function(){
    autoScrollMessages();
    focusComposer();
    attachAutosize();
    attachMembersModal();
  });
})();
