<footer class="border-t py-4 md:py-5 mt-auto">
    <div class="max-w-4xl mx-auto px-5">
        <div class="flex flex-col items-center justify-between gap-3 md:flex-row">
            <p class="text-center text-[11px] text-muted-foreground">
                &copy; <?= date('Y'); ?> Hantuin-v2. All rights reserved.
            </p>
        </div>
    </div>
</footer>

<script>
document.addEventListener('contextmenu', function (e) {
    var tag = e.target.tagName.toLowerCase();
    if (tag === 'img' || tag === 'svg' || tag === 'use' || tag === 'path' ||
        tag === 'circle' || tag === 'rect' || tag === 'line' || tag === 'polyline' ||
        tag === 'polygon' || tag === 'ellipse') {
        e.preventDefault();
    }
    if (e.target.closest && e.target.closest('svg')) {
        e.preventDefault();
    }
});
</script>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html>
