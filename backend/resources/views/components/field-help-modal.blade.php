{{--
  Shared help overlay for training matrix field explanations.
  Renders above create/edit modals (z-index 100) and closes independently.
--}}
<div
    x-data="{
        show: false,
        title: '',
        body: '',
        items: [],
        open(detail) {
            this.title = detail?.title || 'Help';
            this.body = detail?.body || '';
            this.items = Array.isArray(detail?.items) ? detail.items : [];
            this.show = true;
        },
        close() {
            this.show = false;
        }
    }"
    x-on:open-field-help.window="open($event.detail)"
    x-on:keydown.escape.window="
        if (show) {
            $event.stopImmediatePropagation();
            close();
        }
    "
    x-cloak
    x-show="show"
    class="admin-field-help-overlay fixed inset-0 overflow-y-auto px-4 py-8 sm:px-6"
    style="display: none; z-index: 100;"
    data-field-help-modal
    :data-open="show ? 'true' : 'false'"
>
    <div
        class="absolute inset-0 bg-[#16324a]/50 backdrop-blur-[2px]"
        x-on:click="close()"
    ></div>

    <div
        class="admin-modal-panel relative z-10 mx-auto w-full"
        style="max-width: 800px;"
        x-on:click.stop
        role="dialog"
        aria-modal="true"
        :aria-label="title"
    >
        <div class="admin-modal-header">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Field help</p>
                <h3 class="mt-1 text-lg font-semibold text-brand-header" x-text="title"></h3>
            </div>
            <button type="button" class="btn-icon" x-on:click="close()" aria-label="Close help">&times;</button>
        </div>
        <div class="admin-modal-body space-y-4">
            <p
                x-show="body"
                class="whitespace-pre-line text-sm leading-relaxed text-sh-text"
                x-text="body"
            ></p>

            <ul x-show="items.length" class="space-y-3" x-cloak>
                <template x-for="(item, index) in items" :key="index">
                    <li class="rounded-[12px] border border-sh-border bg-gradient-to-b from-[#f7fbff] to-white px-4 py-3">
                        <p class="text-sm font-semibold text-brand-header" x-text="item.label"></p>
                        <p class="mt-1 text-sm leading-relaxed text-sh-mid" x-text="item.description"></p>
                    </li>
                </template>
            </ul>
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="btn-brand" x-on:click="close()">Got it</button>
        </div>
    </div>
</div>
