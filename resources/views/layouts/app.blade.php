@extends('layouts.master')

@section('content')
    <div class="d-flex min-vh-100">

        <!-- Sidebar Wrapper — no width of its own; it takes the sidebar's width (280px or 72px collapsed) -->
        <div id="sidebarWrapper" class="sidebar-wrapper">
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
            flex-shrink: 0;
            height: 100vh;
            position: sticky;
            top: 0;
            overflow: visible;   /* lets the collapsed-menu tooltips show beside the sidebar */
            z-index: 1035;
        }
    </style>
@endsection