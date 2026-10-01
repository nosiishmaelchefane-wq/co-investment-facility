@extends('layouts.master')

@section('content')
    <div class="d-flex min-vh-100">

        <!-- Sidebar Wrapper -->
        <div id="sidebarWrapper" class="sidebar-wrapper" style="flex-shrink: 0; height: 100vh; position: sticky; top: 0; overflow: hidden;">
            @include('layouts.sidebar.menu')
        </div>

        <!-- Main Content Area -->
        <div id="mainContent" class="flex-grow-1 d-flex flex-column" style="min-width: 0;">

            <!-- Navigation Bar -->
            <div class="sticky-top">
                @include('layouts.navigation')
            </div>

            <!-- Page Content -->
            <main class="p-1 flex-grow-1">
                {{ $slot }}
         
            </main>

        </div>

    </div>

    <style>
        .sidebar-wrapper {
            width: 280px;
            transition: width 0.3s ease;
        }

        .sidebar-wrapper.collapsed {
            width: 72px;
        }
    </style>

    <script>
    (function() {
        window.addEventListener('sidebarToggle', function() {
            const sidebarWrapper = document.getElementById('sidebarWrapper');
            if (sidebarWrapper) {
                sidebarWrapper.classList.toggle('collapsed');
            }
        });
    })();
    </script>
@endsection