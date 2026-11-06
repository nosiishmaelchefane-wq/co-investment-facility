<?php $__env->startSection('content'); ?>
    <div class="d-flex min-vh-100">

        <!-- Sidebar Wrapper — no width of its own; it takes the sidebar's width (280px or 72px collapsed) -->
        <div id="sidebarWrapper" class="sidebar-wrapper">
            <?php echo $__env->make('layouts.sidebar.menu', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>

        <!-- Main Content Area -->
        <div id="mainContent" class="flex-grow-1 d-flex flex-column" style="min-width: 0;">

            <!-- Navigation Bar -->
            <div class="sticky-top">
                <?php echo $__env->make('layouts.navigation', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </div>

            <!-- Page Content -->
            <main class="p-1 flex-grow-1">
                <?php echo e($slot); ?>

            </main>

        </div>

    </div>

    <style>
        .sidebar-wrapper {
            flex-shrink: 0;
            height: 100vh;
            position: sticky;
            top: 0;
            overflow: visible;   /* lets the collapsed-menu tooltips show beside the sidebar */
            z-index: 1035;
        }
    </style>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.master', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\co-investment-facility\resources\views/layouts/app.blade.php ENDPATH**/ ?>