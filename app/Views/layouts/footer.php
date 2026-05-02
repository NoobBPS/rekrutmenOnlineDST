        </div>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> PT Digdaya Solusi Teknologi</p>
        </div>
    </footer>

    <script>
    (function () {
        var toggle = document.getElementById('menu-toggle');
        var menu = document.getElementById('nav-menu');
        if (!toggle || !menu) return;

        toggle.addEventListener('click', function () {
            menu.classList.toggle('open');
        });
    })();
    </script>
</body>
</html>