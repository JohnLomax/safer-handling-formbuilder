<x-app-layout>
    <x-slot name="header">
        <x-admin.page-header
            title="System configuration"
            description="PHP runtime, loaded extensions, and database connection details for this admin server."
        />
    </x-slot>

    <div class="admin-shell space-y-6">
        @include('admin.partials.alerts')

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="admin-stat-card">
                <p class="text-sm font-medium text-sh-mid">PHP version</p>
                <p class="mt-2 text-3xl font-semibold text-sh-text">{{ $phpVersion }}</p>
                <p class="mt-1 text-xs text-sh-mid">{{ $phpSapi }} · Laravel {{ $laravelVersion }}</p>
            </div>
            <div class="admin-stat-card">
                <p class="text-sm font-medium text-sh-mid">Database</p>
                <p class="mt-2 text-3xl font-semibold text-sh-text">{{ $database['label'] }}</p>
                <p class="mt-1 text-xs text-sh-mid">
                    @if ($database['connected'])
                        Connected · {{ $database['driver'] }}
                    @else
                        Not connected
                    @endif
                </p>
            </div>
            <div class="admin-stat-card">
                <p class="text-sm font-medium text-sh-mid">PHP extensions</p>
                <p class="mt-2 text-3xl font-semibold text-sh-text">{{ $extensionCount }}</p>
                <p class="mt-1 text-xs text-sh-mid">
                    @if (count($missingRequired) === 0)
                        Common requirements present
                    @else
                        {{ count($missingRequired) }} recommended missing
                    @endif
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="brand-panel space-y-4">
                <div>
                    <h3 class="text-base font-semibold text-brand-header">Runtime</h3>
                    <p class="mt-1 text-sm text-sh-mid">Server PHP environment for this application.</p>
                </div>
                <div class="admin-table-wrap !rounded-[12px]">
                    <table class="admin-table">
                        <tbody>
                            <tr>
                                <th class="w-[40%] text-left">PHP version</th>
                                <td class="font-medium text-sh-text">{{ $phpVersion }}</td>
                            </tr>
                            <tr>
                                <th class="text-left">SAPI</th>
                                <td>{{ $phpSapi }}</td>
                            </tr>
                            <tr>
                                <th class="text-left">Operating system</th>
                                <td>{{ $phpOs }}</td>
                            </tr>
                            <tr>
                                <th class="text-left">Timezone</th>
                                <td>{{ $timezone }}</td>
                            </tr>
                            <tr>
                                <th class="text-left">Memory limit</th>
                                <td>{{ $memoryLimit }}</td>
                            </tr>
                            <tr>
                                <th class="text-left">Max execution time</th>
                                <td>{{ $maxExecutionTime === '0' ? 'Unlimited' : $maxExecutionTime.'s' }}</td>
                            </tr>
                            <tr>
                                <th class="text-left">Upload max filesize</th>
                                <td>{{ $uploadMaxFilesize }}</td>
                            </tr>
                            <tr>
                                <th class="text-left">Post max size</th>
                                <td>{{ $postMaxSize }}</td>
                            </tr>
                            <tr>
                                <th class="text-left">Laravel</th>
                                <td>{{ $laravelVersion }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="brand-panel space-y-4">
                <div>
                    <h3 class="text-base font-semibold text-brand-header">Database</h3>
                    <p class="mt-1 text-sm text-sh-mid">Active Laravel database connection.</p>
                </div>
                <div class="admin-table-wrap !rounded-[12px]">
                    <table class="admin-table">
                        <tbody>
                            <tr>
                                <th class="w-[40%] text-left">Type</th>
                                <td class="font-medium text-sh-text">{{ $database['label'] }}</td>
                            </tr>
                            <tr>
                                <th class="text-left">Driver</th>
                                <td><code class="rounded bg-sh-surface px-1.5 py-0.5 text-xs">{{ $database['driver'] }}</code></td>
                            </tr>
                            <tr>
                                <th class="text-left">Connection</th>
                                <td>{{ $database['connection'] }}</td>
                            </tr>
                            <tr>
                                <th class="text-left">Database</th>
                                <td>{{ $database['database'] }}</td>
                            </tr>
                            <tr>
                                <th class="text-left">Host</th>
                                <td>{{ $database['host'] }}</td>
                            </tr>
                            <tr>
                                <th class="text-left">Server version</th>
                                <td>{{ $database['version'] }}</td>
                            </tr>
                            <tr>
                                <th class="text-left">Status</th>
                                <td>
                                    @if ($database['connected'])
                                        <span class="inline-flex items-center rounded-full bg-[#f3f9e8] px-2.5 py-0.5 text-xs font-semibold text-[#3f6d1c]">Connected</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-[#fff1f1] px-2.5 py-0.5 text-xs font-semibold text-[#9b1c1c]">Unavailable</span>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                @if ($database['error'])
                    <p class="rounded-[10px] border border-[#f5c2c2] bg-[#fff7f7] px-3 py-2 text-sm text-[#9b1c1c]">
                        {{ $database['error'] }}
                    </p>
                @endif
            </div>
        </div>

        <div class="brand-panel space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-brand-header">PHP extensions</h3>
                    <p class="mt-1 text-sm text-sh-mid">
                        {{ $extensionCount }} loaded extension{{ $extensionCount === 1 ? '' : 's' }}.
                        Common Laravel / form requirements are highlighted.
                    </p>
                </div>
                @if (count($missingRequired) > 0)
                    <p class="rounded-[10px] border border-[#f5d9a8] bg-[#fff8eb] px-3 py-2 text-sm text-[#9a6700]">
                        Missing recommended: {{ implode(', ', $missingRequired) }}
                    </p>
                @endif
            </div>

            <div class="flex flex-wrap gap-2">
                @foreach ($extensions as $extension)
                    @php
                        $isRequired = in_array(strtolower($extension), array_map('strtolower', $requiredExtensions), true);
                    @endphp
                    <span
                        @class([
                            'inline-flex items-center rounded-[10px] border px-2.5 py-1 text-xs font-medium',
                            'border-[#cfe4b8] bg-[#f3f9e8] text-[#3f6d1c]' => $isRequired,
                            'border-sh-border bg-white text-sh-text' => ! $isRequired,
                        ])
                    >{{ $extension }}</span>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
