<?php if (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['role'])): ?>
        </div>
    </main>
</div>
<?php else: ?>
        </div>
    </div>
<?php endif; ?>
</body>
</html>