<x-app-layout>
    <x-slot name="header">
        <x-admin.page-header
            title="Enquiries"
            description="Search, filter, and open customer forms. Live shows the last 30 days; older enquiries move to Archive."
        >
            <x-slot name="actions">
                <form method="POST" action="{{ route('admin.enquiries.create') }}" target="_blank" rel="noopener">
                    @csrf
                    <button type="submit" class="btn-brand inline-flex items-center gap-2">
                        <svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Create new enquiry
                    </button>
                </form>
            </x-slot>
        </x-admin.page-header>
    </x-slot>

    <div
        class="admin-shell"
        id="enquiries-app"
        data-index-url="{{ route('admin.enquiries.index') }}"
        data-initial-tab="{{ $tab }}"
        data-page-ids='@json($enquiries->pluck('id')->map(fn ($id) => (int) $id)->values())'
        x-data="enquiriesIndex"
    >
        <div class="brand-panel !p-0">
            @include('admin.partials.alerts')

            <div id="enquiries-panel">
                @include('admin.enquiries._index_panel')
            </div>
        </div>

        <x-modal name="confirm-bulk-enquiry-delete" maxWidth="lg" focusable>
            <form method="POST" action="{{ route('admin.enquiries.bulk-destroy') }}" class="p-6">
                @csrf
                @method('DELETE')
                <template x-for="id in selected" :key="id">
                    <input type="hidden" name="enquiry_ids[]" :value="id" />
                </template>

                <h3 class="text-lg font-semibold text-brand-header">
                    Are you sure you want to delete
                    <span x-text="selectedCount"></span>
                    <span x-text="selectedCount === 1 ? 'enquiry' : 'enquiries'"></span>?
                </h3>
                <p class="mt-2 text-sm text-sh-mid">
                    This removes the selected enquiries from this admin system. Monday items are not deleted.
                    Any active Forge bookings for these enquiries will be cancelled first.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" class="btn-brand-outline" x-on:click="$dispatch('close-modal', 'confirm-bulk-enquiry-delete')">
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="btn-brand-outline text-red-700 border-red-200 hover:border-red-400 hover:text-red-800"
                    >
                        Yes, delete selected
                    </button>
                </div>
            </form>
        </x-modal>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('enquiriesIndex', () => ({
                tab: 'live',
                selected: [],
                pageIds: [],
                loading: false,
                requestId: 0,

                init() {
                    const root = this.$el;
                    this.tab = root.dataset.initialTab === 'archive' ? 'archive' : 'live';
                    try {
                        this.pageIds = JSON.parse(root.dataset.pageIds || '[]').map(Number);
                    } catch (e) {
                        this.pageIds = [];
                    }

                    // Event delegation — survives panel HTML swaps without Alpine.initTree.
                    root.addEventListener('click', (event) => {
                        const tabBtn = event.target.closest('[data-enquiry-tab]');
                        if (tabBtn) {
                            event.preventDefault();
                            this.switchTab(tabBtn.getAttribute('data-enquiry-tab'));
                            return;
                        }

                        const statusLink = event.target.closest('[data-enquiry-status]');
                        if (statusLink) {
                            event.preventDefault();
                            this.applyStatus(statusLink.getAttribute('data-enquiry-status'));
                            return;
                        }

                        const clearBtn = event.target.closest('[data-enquiry-clear]');
                        if (clearBtn) {
                            event.preventDefault();
                            this.clearFilters();
                            return;
                        }

                        const pageLink = event.target.closest('#enquiries-panel .pagination a, #enquiries-panel nav[role=\"navigation\"] a');
                        if (pageLink && pageLink.href) {
                            event.preventDefault();
                            this.loadPanel(new URL(pageLink.href, window.location.origin), true);
                        }
                    });

                    window.addEventListener('popstate', () => {
                        const url = new URL(window.location.href);
                        this.tab = url.searchParams.get('tab') === 'archive' ? 'archive' : 'live';
                        this.loadPanel(url, false);
                    });
                },

                toggleAll(checked) {
                    this.selected = checked ? [...this.pageIds] : [];
                },
                isSelected(id) {
                    return this.selected.includes(id);
                },
                toggleOne(id, checked) {
                    if (checked) {
                        if (! this.selected.includes(id)) {
                            this.selected.push(id);
                        }
                        return;
                    }
                    this.selected = this.selected.filter((value) => value !== id);
                },
                get allSelected() {
                    return this.pageIds.length > 0 && this.pageIds.every((id) => this.selected.includes(id));
                },
                get selectedCount() {
                    return this.selected.length;
                },
                openBulkDelete() {
                    if (this.selectedCount < 1) {
                        return;
                    }
                    this.$dispatch('open-modal', 'confirm-bulk-enquiry-delete');
                },

                buildUrl({ tab = this.tab, status = null, clearSearch = false } = {}) {
                    const url = new URL(window.location.href);
                    const params = url.searchParams;

                    params.delete('page');

                    if (clearSearch) {
                        params.delete('q');
                        params.delete('status');
                        params.delete('per_page');
                        params.delete('tab');
                        return url;
                    }

                    if (tab === 'archive') {
                        params.set('tab', 'archive');
                    } else {
                        params.delete('tab');
                    }

                    if (status && status !== 'all') {
                        params.set('status', status);
                    } else {
                        params.delete('status');
                    }

                    return url;
                },

                async switchTab(nextTab) {
                    if (nextTab !== 'live' && nextTab !== 'archive') {
                        return;
                    }
                    if (this.loading || this.tab === nextTab) {
                        return;
                    }
                    this.tab = nextTab;
                    await this.loadPanel(this.buildUrl({ tab: nextTab, status: null }), true);
                },

                async applyStatus(status) {
                    await this.loadPanel(this.buildUrl({ tab: this.tab, status }), true);
                },

                async clearFilters() {
                    this.tab = 'live';
                    await this.loadPanel(this.buildUrl({ clearSearch: true }), true);
                },

                syncFromPanel() {
                    const marker = document.querySelector('#enquiries-panel [data-page-ids]');
                    if (! marker) {
                        this.pageIds = [];
                        return;
                    }
                    try {
                        this.pageIds = JSON.parse(marker.getAttribute('data-page-ids') || '[]').map(Number);
                    } catch (e) {
                        this.pageIds = [];
                    }
                    const currentTab = marker.getAttribute('data-current-tab');
                    if (currentTab === 'live' || currentTab === 'archive') {
                        this.tab = currentTab;
                    }
                    this.updateTabStyles();
                },

                updateTabStyles() {
                    document.querySelectorAll('[data-enquiry-tab]').forEach((btn) => {
                        const active = btn.getAttribute('data-enquiry-tab') === this.tab;
                        btn.classList.toggle('bg-white', active);
                        btn.classList.toggle('text-brand-header', active);
                        btn.classList.toggle('shadow-sm', active);
                        btn.classList.toggle('text-sh-mid', ! active);
                        btn.classList.toggle('hover:text-brand-header', ! active);
                        if (active) {
                            btn.setAttribute('aria-current', 'page');
                        } else {
                            btn.removeAttribute('aria-current');
                        }
                        const badge = btn.querySelector('[data-enquiry-tab-badge]');
                        if (badge) {
                            badge.classList.toggle('bg-[#e8f4ff]', active);
                            badge.classList.toggle('text-brand', active);
                            badge.classList.toggle('bg-white/80', ! active);
                            badge.classList.toggle('text-sh-mid', ! active);
                        }
                    });

                    const hint = document.querySelector('[data-enquiry-tab-hint]');
                    if (hint) {
                        hint.textContent = this.tab === 'archive'
                            ? 'Archive shows enquiries older than 30 days.'
                            : 'Live shows enquiries from the last 30 days. Older ones move to Archive automatically.';
                    }

                    const label = document.querySelector('[data-enquiry-tab-label]');
                    if (label) {
                        label.textContent = this.tab === 'archive' ? 'Archive' : 'Live';
                    }
                },

                async loadPanel(url, pushHistory) {
                    const requestId = ++this.requestId;
                    this.loading = true;
                    this.selected = [];
                    this.updateTabStyles();

                    const panel = document.getElementById('enquiries-panel');
                    if (panel) {
                        panel.classList.add('opacity-60', 'pointer-events-none');
                    }

                    try {
                        const response = await fetch(url.toString(), {
                            headers: {
                                Accept: 'text/html',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                        });

                        if (! response.ok) {
                            throw new Error('Could not load enquiries (' + response.status + ').');
                        }

                        const html = await response.text();
                        if (requestId !== this.requestId) {
                            return;
                        }

                        if (! panel) {
                            throw new Error('Enquiry panel missing.');
                        }

                        panel.innerHTML = html;
                        if (window.Alpine) {
                            window.Alpine.initTree(panel);
                        }
                        this.syncFromPanel();

                        if (pushHistory) {
                            history.pushState({ enquiriesTab: this.tab }, '', url.toString());
                        }
                    } catch (error) {
                        if (requestId !== this.requestId) {
                            return;
                        }
                        console.error(error);
                        // Keep the user on the page — do not force a full reload.
                        this.syncFromPanel();
                    } finally {
                        if (requestId === this.requestId) {
                            this.loading = false;
                            if (panel) {
                                panel.classList.remove('opacity-60', 'pointer-events-none');
                            }
                            this.updateTabStyles();
                        }
                    }
                },
            }));
        });

    </script>
</x-app-layout>
