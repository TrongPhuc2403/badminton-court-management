<style>
    /* CSS dành riêng cho thanh Sidebar */
    .sidebar {
        width: 260px; /* Độ rộng của thanh menu */
        min-height: 100vh; /* Cao bằng toàn bộ màn hình */
        background-color: #ffffff; /* Nền trắng */
        border-right: 1px solid #e2e5e8; /* Đường kẻ xám nhạt ngăn cách với nội dung */
        display: flex;
        flex-direction: column;
    }
    
    /* Phần Logo */
    .sidebar-brand {
        padding: 24px 20px;
        font-size: 1.25rem;
        font-weight: 600;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        border-bottom: 1px solid #f1f5f9;
    }
    
    /* FIX LỖI LOGO BỊ LỆCH: Ép buộc căn giữa tuyệt đối */
    .brand-icon {
        width: 36px;
        height: 36px;
        background-color: #0d6efd; 
        color: white;
        border-radius: 10px; /* Bo góc logo */
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-size: 1.1rem;
        line-height: 1; /* Chống lệch icon */
        padding: 0;
    }

    .brand-icon i {
        display: block;
        margin: 0;
    }

    /* Các nút menu */
    .sidebar-nav {
        padding: 16px 12px;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    /* Thẻ bọc ngoài nút chức năng */
    .nav-link-custom {
        position: relative; /* Để chứa vạch màu xanh bên trái */
        color: #475569; /* Chữ màu xám */
        padding: 12px 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        font-weight: 500;
        border-radius: 8px;
        transition: all 0.2s ease-in-out;
        overflow: hidden; /* Cắt phần viền bị lố */
    }

    /* HIỆU ỨNG KHI ĐƯỢC CHỌN VÀ DI CHUỘT VÀO (GIỐNG HÌNH 2) */
    .nav-link-custom.active,
    .nav-link-custom:hover {
        background-color: #495057; /* Nền xám đen */
        color: #ffffff; /* Chữ màu trắng */
    }

    /* Tạo vạch màu xanh dương bên trái */
    .nav-link-custom.active::before,
    .nav-link-custom:hover::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px; /* Độ dày của vạch xanh */
        background-color: #0d6efd; /* Màu xanh dương */
        border-radius: 8px 0 0 8px;
    }

    .nav-link-custom i {
        font-size: 1.2rem;
        width: 24px;
        text-align: center;
    }

    /* Phần User Profile (Quản trị viên) ở dưới cùng */
    .user-profile {
        margin-top: auto; /* Đẩy phần này xuống sát đáy */
        padding: 20px;
        border-top: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .user-avatar {
        width: 44px;
        height: 44px;
        background: linear-gradient(135deg, #8b5cf6, #3b82f6); /* Gradient tím xanh */
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 1rem;
    }

    .user-info p {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 600;
        color: #1e293b;
    }

    .user-info small {
        color: #64748b;
        font-size: 0.8rem;
    }
</style>

<div class="sidebar">
    <a href="#" class="sidebar-brand">
        <div class="brand-icon">
            <i class="fa-solid fa-location-dot"></i>
        </div>
        BadmintonPro
    </a>

    <div class="sidebar-nav">
        <a href="#" class="nav-link-custom">
            <i class="fa-solid fa-border-all"></i> Tổng quan
        </a>
        <a href="courts.php" class="nav-link-custom active">
            <i class="fa-solid fa-location-dot"></i> Quản lý sân
        </a>
        <a href="#" class="nav-link-custom">
            <i class="fa-regular fa-calendar"></i> Đặt chỗ
        </a>
        <a href="#" class="nav-link-custom">
            <i class="fa-solid fa-user-group"></i> Khách hàng
        </a>
        <a href="#" class="nav-link-custom">
            <i class="fa-solid fa-chart-line"></i> Doanh thu
        </a>
    </div>

    <div class="user-profile">
        <div class="user-avatar">QT</div>
        <div class="user-info">
            <p>Quản trị viên</p>
            <small>admin@badminton.com</small>
        </div>
    </div>
</div>