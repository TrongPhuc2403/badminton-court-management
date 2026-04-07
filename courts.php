<?php 
include 'includes/database.php'; 

// --- 1. XỬ LÝ LOGIC BACKEND ---

// Chức năng: Thêm sân mới
if (isset($_POST['add_court'])) {
    $name = $_POST['court_name'];
    $floor = $_POST['floor'];
    $type = $_POST['court_type'];
    $price = $_POST['price'];
    
    $sql = "INSERT INTO courts (court_name, floor, court_type, price) VALUES ('$name', '$floor', '$type', '$price')";
    mysqli_query($conn, $sql);
    header("Location: courts.php"); // Refresh lại trang
}

// Chức năng: Đổi trạng thái
if (isset($_POST['update_status'])) {
    $id = $_POST['court_id'];
    $new_status = $_POST['new_status'];
    
    $sql = "UPDATE courts SET status = '$new_status' WHERE id = $id";
    mysqli_query($conn, $sql);
    header("Location: courts.php");
}

// Chức năng: Xóa sân
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    mysqli_query($conn, "DELETE FROM courts WHERE id = $id");
    header("Location: courts.php");
}

// --- 2. LẤY DỮ LIỆU TỪ DATABASE ---
$result = mysqli_query($conn, "SELECT * FROM courts");
$courts = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Tính toán thống kê
$count_ready = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM courts WHERE status='ready'"))['total'];
$count_in_use = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM courts WHERE status='in_use'"))['total'];
$count_maintenance = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM courts WHERE status='maintenance'"))['total'];

$status_config = [
    'ready' => ['text' => 'Sẵn sàng', 'icon' => 'fa-power-off', 'color_class' => 'success'],
    'in_use' => ['text' => 'Đang sử dụng', 'icon' => 'fa-power-off', 'color_class' => 'primary'],
    'maintenance' => ['text' => 'Bảo trì', 'icon' => 'fa-ban', 'color_class' => 'danger']
];

include 'includes/header.php'; 
include 'includes/sidebar.php'; 
?>

<style>
    /* Class độc quyền để tránh bị Bootstrap ghi đè */
    .custom-court-card {
        border: 2px solid #e2e5e8 !important; /* Màu viền xám đậm và rõ nét giống hình 2 */
        border-radius: 16px !important;       /* Bo góc 16px */
        box-shadow: none !important;          /* Xóa hoàn toàn bóng đổ */
        background-color: #ffffff !important;
    }
    
    /* Box chứa icon: Đảm bảo hình tròn và icon luôn nằm chính giữa */
    .icon-box { 
        width: 44px; 
        height: 44px; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        font-size: 1.25rem; 
        flex-shrink: 0; /* Không bị bóp méo trên màn hình nhỏ */
    }
    
    .status-bar { padding: 10px; font-weight: 500; }
    
    /* Chỉnh sửa viền nút Đổi trạng thái và nút Xóa cho đồng bộ */
    .btn-outline-custom { border-color: #e2e5e8; color: #495057; }
    .btn-outline-custom:hover { background-color: #f8f9fa; }
    .btn-outline-danger { border-color: #e2e5e8; } 
</style>

<div class="content flex-grow-1 p-4 bg-white">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1">Quản lý sân</h3>
            <p class="text-muted mb-0">Quản lý thông tin các sân cầu lông</p>
        </div>
        <button class="btn btn-primary px-4 py-2 rounded-3" data-bs-toggle="modal" data-bs-target="#addCourtModal">
            <i class="fa-solid fa-plus"></i> Thêm sân mới
        </button>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card custom-court-card"><div class="card-body d-flex align-items-center gap-3"><div class="icon-box bg-success-subtle text-success rounded-3"><i class="fa-solid fa-power-off"></i></div><div><div class="text-muted small">Sẵn sàng</div><h4 class="mb-0 fw-bold"><?= $count_ready ?></h4></div></div></div>
        </div>
        <div class="col-md-4">
            <div class="card custom-court-card"><div class="card-body d-flex align-items-center gap-3"><div class="icon-box bg-primary-subtle text-primary rounded-3"><i class="fa-solid fa-power-off"></i></div><div><div class="text-muted small">Đang sử dụng</div><h4 class="mb-0 fw-bold"><?= $count_in_use ?></h4></div></div></div>
        </div>
        <div class="col-md-4">
            <div class="card custom-court-card"><div class="card-body d-flex align-items-center gap-3"><div class="icon-box bg-danger-subtle text-danger rounded-3"><i class="fa-solid fa-ban"></i></div><div><div class="text-muted small">Bảo trì</div><h4 class="mb-0 fw-bold"><?= $count_maintenance ?></h4></div></div></div>
        </div>
    </div>

    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
        <?php foreach ($courts as $court): 
            $conf = $status_config[$court['status']];
        ?>
            <div class="col">
                <div class="card custom-court-card h-100 p-3">
                    
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="icon-box bg-primary-subtle text-primary rounded-circle">
                                <i class="fa-solid fa-location-dot"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold fs-5"><?= $court['court_name'] ?></h6>
                                <small class="text-muted"><?= $court['floor'] ?></small>
                            </div>
                        </div>
                        <span class="badge <?= ($court['court_type'] == 'VIP') ? 'bg-dark' : 'bg-light text-dark border' ?> rounded-pill px-3 py-2"><?= $court['court_type'] ?></span>
                    </div>
                    <div class="status-bar bg-<?= $conf['color_class'] ?>-subtle text-<?= $conf['color_class'] ?> text-center rounded-2 mb-4 py-2">
                        <i class="fa-solid <?= $conf['icon'] ?>"></i> <?= $conf['text'] ?>
                    </div>

                    <div class="mb-4 mt-auto">
                        <small class="text-muted d-block mb-1">Giá thuê</small>
                        <div><span class="fs-5 fw-bold"><?= number_format($court['price'], 3) ?> VNĐ</span><span class="text-muted">/giờ</span></div>
                    </div>

                    <div class="d-flex gap-2 mt-auto">
                        <button class="btn btn-outline-custom w-100 rounded-3" 
                                onclick="openStatusModal(<?= $court['id'] ?>, '<?= $court['status'] ?>')">
                            <i class="fa-regular fa-pen-to-square"></i> Đổi trạng thái
                        </button>
                        <a href="courts.php?delete_id=<?= $court['id'] ?>" class="btn btn-outline-danger rounded-3 px-3" onclick="return confirm('Bạn có chắc chắn muốn xóa sân này?')">
                            <i class="fa-regular fa-trash-can"></i>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="modal fade" id="addCourtModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="POST">
      <div class="modal-header">
        <h5 class="modal-title">Thêm sân mới</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3"><label>Tên sân</label><input type="text" name="court_name" class="form-control" required></div>
        <div class="mb-3"><label>Tầng</label><input type="text" name="floor" class="form-control" required></div>
        <div class="mb-3">
            <label>Loại sân</label>
            <select name="court_type" class="form-control">
                <option value="Thường">Thường</option>
                <option value="VIP">VIP</option>
            </select>
        </div>
        <div class="mb-3"><label>Giá thuê (VNĐ/giờ)</label><input type="number" step="0.001" name="price" class="form-control" required></div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="add_court" class="btn btn-primary w-100">Lưu thông tin</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="statusModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <form class="modal-content" method="POST">
      <div class="modal-header"><h5 class="modal-title">Đổi trạng thái</h5></div>
      <div class="modal-body">
        <input type="hidden" name="court_id" id="modal_court_id">
        <select name="new_status" id="modal_status" class="form-select">
            <option value="ready">Sẵn sàng</option>
            <option value="in_use">Đang sử dụng</option>
            <option value="maintenance">Bảo trì</option>
        </select>
      </div>
      <div class="modal-footer text-center">
        <button type="submit" name="update_status" class="btn btn-success w-100">Cập nhật</button>
      </div>
    </form>
  </div>
</div>

<script>
function openStatusModal(id, currentStatus) {
    document.getElementById('modal_court_id').value = id;
    document.getElementById('modal_status').value = currentStatus;
    var myModal = new bootstrap.Modal(document.getElementById('statusModal'));
    myModal.show();
}
</script>

<?php include 'includes/footer.php'; ?>