<?php if (isset($_SESSION['user_id'])): ?>
        </main>
    </div>
</div>
<?php endif; ?>
<script src="<?= e(app_url('assets/js/app.js')) ?>"></script>
<script>
(function(){
  function hideAppLoader(){
    var loader = document.getElementById('appLoader');
    if(!loader) return;
    loader.classList.add('is-hidden');
    setTimeout(function(){ if(loader && loader.parentNode){ loader.style.display = 'none'; } }, 450);
  }
  if(document.readyState === 'complete' || document.readyState === 'interactive'){
    setTimeout(hideAppLoader, 50);
  } else {
    document.addEventListener('DOMContentLoaded', function(){ setTimeout(hideAppLoader, 50); });
  }
  window.addEventListener('load', hideAppLoader);
  window.addEventListener('pageshow', hideAppLoader);
  setTimeout(hideAppLoader, 1200);
})();
</script>
</body>
</html>
