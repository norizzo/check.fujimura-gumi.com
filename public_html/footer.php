<!-- Font Awesomeアイコン -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<!-- フッターメニュー -->
<footer class="bg-light text-muted py-3" style="position: fixed; bottom: 0; left: 0; right: 0; z-index: 1000;">
    <div class="container">
        <div class="row">
            
            <div class="col text-center">
                <a href="inspection_top.php" class="text-muted">
                    <i class="fa-solid fa-person-digging fa-lg"></i>
                    <p>場所・機械・道具</p>
                </a>
            </div>
            <div class="col text-center">
                <a href="get_staffing.php" class="text-muted">
                    <i class="fa-solid fa-truck-monster fa-lg"></i>
                    <p>重機</p>
                </a>
            </div>
            <!-- <div class="col text-center">
                <a href="generator.php" class="text-muted">
                    <i class="fas fa-car-battery fa-lg"></i>
                    <p>溶接機</p>
                </a>
            </div> -->
            <div class="col text-center">
                <a href="view_records.php" class="text-muted">
                    <i class="fas fa-eye fa-lg"></i>
                    <p>閲覧・修正</p>
                </a>
            </div>
            <?php
            $allowed_users = ['田中 利憲', '本山 塁', '杉本 義夫', '小島 聡明', '小山 哲郎', '藤村 英明, 若月 士竜'];
            if (isset($_SESSION['display_name']) && in_array($_SESSION['display_name'], $allowed_users)): ?>
            <div class="col text-center">
                <a href="master_edit.php" class="text-muted">
                    <i class="fas fa-screwdriver-wrench fa-lg"></i>
                    <p>マスタ修正</p>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</footer>

<script>
// フッター固定時のコンテンツ調整
document.addEventListener('DOMContentLoaded', function() {
    const footer = document.querySelector('footer');
    const footerHeight = footer.offsetHeight;
    document.body.style.paddingBottom = footerHeight + 'px';
});
</script>