</div> <footer>
    <?php if (isset($_GET['subscribe_msg'])): ?>
        <script>
            // Простой алерт, чтобы не ломать верстку вставками
            window.onload = function() {
                <?php if ($_GET['subscribe_msg'] == 'success'): ?>
                    alert("Спасибо! Вы успешно подписались на рассылку. Проверьте почту.");
                <?php elseif ($_GET['subscribe_msg'] == 'exists'): ?>
                    alert("Вы уже подписаны на нашу рассылку.");
                <?php elseif ($_GET['subscribe_msg'] == 'invalid'): ?>
                    alert("Пожалуйста, введите корректный Email.");
                <?php else: ?>
                    alert("Произошла ошибка при подписке.");
                <?php endif; ?>
                
                // Очистка URL от параметра (чтобы алерт не выскакивал при обновлении страницы)
                const url = new URL(window.location.href);
                url.searchParams.delete('subscribe_msg');
                window.history.replaceState(null, null, url);
            }
        </script>
    <?php endif; ?>

    <div class="topfooter">
        <div class="container-wrap topfooter-inner">
            <div>
                <h1>Подпишись на нашу рассылку.</h1>
                <h3 style="opacity: 0.8; font-weight: normal;">Будьте в курсе всех новостей и акций.</h3>
            </div>
            <div>
                <form action="subscribe.php" method="POST" class="subscribe-form">
                    <input type="email" name="email" placeholder="Ваш Email" required>
                    <button type="submit">Подписаться</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="container-wrap">
        <div class="bottomfooter">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; gap: 15px;">
                    <a href="#"><img src="/image/Twitter.png" alt="Twitter"></a>
                    <a href="#"><img src="/image/instagram.png" alt="Instagram"></a>
                </div>
                <div>
                    Copyright © <?= date('Y') ?> Computer Shop Inc.
                </div>
            </div>
        </div>
    </div>
</footer>
</body>
</html>