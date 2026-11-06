<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    

    <div class="container-fluid p-3 cif-dashboard">
        <style>
            .cif-dashboard .chart-box { min-height: 280px; }
            .cif-dashboard .money-sub { font-size: .78rem; color: #64748b; }
            .cif-dashboard .progress-thin { height: 6px; }
            .cif-dashboard .task-card { border: 1px solid var(--cif-border, #e2e8f0); border-radius: .85rem; background: #fff; height: 100%; }
            .cif-dashboard .task-head { display: flex; gap: .75rem; align-items: flex-start; padding: .9rem 1rem .6rem; }
            .cif-dashboard .task-count { margin-left: auto; font-weight: 700; font-size: 1.15rem; color: var(--cif-navy, #0d1a3a); }
            .cif-dashboard .task-list { list-style: none; margin: 0; padding: 0 1rem .75rem; }
            .cif-dashboard .task-list li + li { border-top: 1px dashed var(--cif-border, #e2e8f0); }
            .cif-dashboard .task-list a { display: flex; gap: .5rem; align-items: baseline; padding: .45rem 0; text-decoration: none; color: inherit; }
            .cif-dashboard .task-list a:hover .task-ref { text-decoration: underline; }
            .cif-dashboard .task-ref { font-weight: 600; color: var(--cif-primary, #29317D); white-space: nowrap; }
            .cif-dashboard .task-icon { width: 38px; height: 38px; font-size: 1rem; }
            .cif-dashboard .stage-tile { border: 1px solid var(--cif-border, #e2e8f0); border-radius: .75rem; padding: .75rem .9rem; background: #fff; height: 100%; }
            .cif-dashboard .stage-n { font-size: 1.5rem; font-weight: 700; color: var(--cif-navy, #0d1a3a); line-height: 1.1; }
            .cif-dashboard .stage-chip { font-size: .72rem; text-decoration: none; }
            .cif-dashboard .role-pill { text-transform: none; letter-spacing: normal; }
        </style>

        
        <script>
            (function () {
                if (window.CifCharts) { window.CifCharts.scan(); return; }

                const RATE = <?php echo e((float) config('cif.usd_to_lsl', 18.00)); ?>;
                const URL  = 'https://cdn.jsdelivr.net/npm/apexcharts@3.54.1/dist/apexcharts.min.js';
                const C    = { primary: '#29317D', accent: '#089E49', navy: '#0d1a3a', amber: '#f59e0b', red: '#dc2626', grey: '#94a3b8', light: '#cbd5e1', info: '#0ea5e9' };
                const fmt   = v => Math.round(v || 0).toLocaleString();
                const money = v => 'M ' + fmt(v) + '  (US$ ' + fmt((v || 0) / RATE) + ')';
                const short = v => v >= 1e6 ? 'M ' + (v / 1e6).toFixed(1) + 'm' : (v >= 1e3 ? 'M ' + Math.round(v / 1e3) + 'k' : 'M ' + fmt(v));
                const base  = {
                    chart: { fontFamily: 'inherit', toolbar: { show: false }, zoom: { enabled: false }, animations: { speed: 350 } },
                    dataLabels: { enabled: false },
                    grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
                    legend: { position: 'bottom', fontSize: '12px' },
                };

                /* ---------- one builder per chart: data from the component → ApexCharts options ---------- */
                const builders = {
                    stages: d => ({
                        ...base,
                        chart: { ...base.chart, type: 'bar', height: 280 },
                        series: [{ name: 'Applications', data: d.data }],
                        xaxis: { categories: d.labels, labels: { formatter: v => Math.round(v) } },
                        plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '60%', distributed: true } },
                        colors: [C.light, C.info, C.primary, C.navy, C.amber, C.accent],
                        dataLabels: { enabled: true, style: { colors: ['#fff'] } },
                        legend: { show: false },
                        tooltip: { y: { formatter: v => v + (v === 1 ? ' application' : ' applications') } },
                    }),
                    outcomes: d => ({
                        ...base,
                        chart: { ...base.chart, type: 'donut', height: 280 },
                        series: d.data,
                        labels: d.labels,
                        colors: [C.primary, C.accent, C.red, C.grey],
                        plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: 'Applications', formatter: w => w.globals.seriesTotals.reduce((a, b) => a + b, 0) } } } } },
                    }),
                    grant: d => ({
                        ...base,
                        chart: { ...base.chart, type: 'donut', height: 260 },
                        series: [Math.round(d.released), Math.round(d.scheduled), Math.round(d.unscheduled)],
                        labels: ['Released', 'In tranches (not yet released)', 'Not yet in a tranche'],
                        colors: [C.accent, C.info, C.light],
                        tooltip: { y: { formatter: money } },
                        plotOptions: { pie: { donut: { size: '70%', labels: { show: true,
                            value: { formatter: v => short(Number(v)) },
                            total: { show: true, label: 'Still to disburse', formatter: w => short(w.globals.seriesTotals[1] + w.globals.seriesTotals[2]) } } } } },
                    }),
                    trend: d => {
                        const series = [{ name: 'Applications submitted', type: 'column', data: d.submitted }];
                        const yaxis  = [{ title: { text: 'Applications' }, labels: { formatter: v => Math.round(v) }, min: 0 }];
                        if (d.money) {
                            series.push({ name: 'Grant released (M)', type: 'line', data: d.released });
                            yaxis.push({ opposite: true, title: { text: 'Maloti' }, labels: { formatter: short }, min: 0 });
                        }
                        return {
                            ...base,
                            chart: { ...base.chart, type: 'line', height: 280 },
                            series, yaxis,
                            labels: d.labels,
                            stroke: { width: d.money ? [0, 3] : [0], curve: 'smooth' },
                            plotOptions: { bar: { borderRadius: 4, columnWidth: '45%' } },
                            colors: [C.primary, C.accent],
                            markers: { size: d.money ? [0, 4] : [0] },
                            tooltip: { shared: true, intersect: false, y: { formatter: (v, { seriesIndex }) => seriesIndex === 1 ? money(v) : v } },
                        };
                    },
                };

                /* ---------- ApexCharts: from the master layout, or loaded here ---------- */
                function withApex(cb) {
                    if (window.ApexCharts) return cb();
                    let tag = document.querySelector('script[data-apexcharts]');
                    if (!tag) {
                        tag = Object.assign(document.createElement('script'), { src: URL });
                        tag.dataset.apexcharts = '1';
                        document.head.appendChild(tag);
                    }
                    tag.addEventListener('load', cb, { once: true });
                    tag.addEventListener('error', () => console.error('[dashboard] ApexCharts could not be loaded from ' + URL));
                }

                /* ---------- draw every chart placeholder that hasn't been drawn yet ---------- */
                function scan() {
                    const els = [...document.querySelectorAll('[data-cif-chart]')].filter(el => el.dataset.cifDrawn !== el.dataset.cifData);
                    if (!els.length) return;
                    withApex(() => els.forEach(el => {
                        const build = builders[el.dataset.cifChart];
                        if (!build) return console.warn('[dashboard] no chart builder for "' + el.dataset.cifChart + '"');
                        try {
                            el._chart?.destroy();
                            el.innerHTML = '';
                            el._chart = new ApexCharts(el, build(JSON.parse(el.dataset.cifData || '{}')));
                            el._chart.render();
                            el.dataset.cifDrawn = el.dataset.cifData;
                        } catch (e) {
                            console.error('[dashboard] chart "' + el.dataset.cifChart + '" failed:', e);   // one bad chart never stops the others
                        }
                    }));
                }

                window.CifCharts = { scan, builders };

                // First paint, after every script in the master has loaded, after wire:navigate…
                document.addEventListener('DOMContentLoaded', scan);
                window.addEventListener('load', scan);
                document.addEventListener('livewire:navigated', scan);

                // …and whenever a lazy component (or a re-render) puts a chart on the page
                let queued = false;
                new MutationObserver(() => {
                    if (queued) return;
                    queued = true;
                    requestAnimationFrame(() => { queued = false; scan(); });
                }).observe(document.documentElement, { childList: true, subtree: true });
            })();
        </script>

        <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('dashboard.header', []);

$__keyOuter = $__key ?? null;

$__key = null;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1357935853-0', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>
        <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('dashboard.kpis', []);

$__keyOuter = $__key ?? null;

$__key = null;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1357935853-1', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>
        <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('dashboard.attention', []);

$__keyOuter = $__key ?? null;

$__key = null;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1357935853-2', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>
        <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('dashboard.pipeline', []);

$__keyOuter = $__key ?? null;

$__key = null;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1357935853-3', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>

        <div class="row g-3 mb-4">
            <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('dashboard.grant', []);

$__keyOuter = $__key ?? null;

$__key = null;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1357935853-4', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>
            <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('dashboard.trend', []);

$__keyOuter = $__key ?? null;

$__key = null;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1357935853-5', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>
        </div>

        <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('dashboard.disbursements', []);

$__keyOuter = $__key ?? null;

$__key = null;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1357935853-6', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>
        <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('dashboard.recent', []);

$__keyOuter = $__key ?? null;

$__key = null;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1357935853-7', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?><?php /**PATH C:\laragon\www\co-investment-facility\resources\views/dashboard/index.blade.php ENDPATH**/ ?>