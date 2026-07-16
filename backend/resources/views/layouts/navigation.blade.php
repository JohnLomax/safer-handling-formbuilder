<nav x-data="{ open: false }" class="brand-header-bar border-b border-[#014a8f] shadow-[0_4px_18px_rgba(2,85,164,0.25)]">
    <div class="relative z-10 mx-auto flex w-full max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
        <div class="flex min-w-0 flex-1 items-center gap-5">
            <a href="{{ route('admin.dashboard') }}" class="shrink-0">
                <x-brand-logo class="max-w-[170px]" />
            </a>
            <div class="hidden sm:block">
                <p class="text-sm font-semibold leading-tight">Admin Portal</p>
                <p class="text-xs text-white/90">Safer Handling</p>
            </div>
        </div>

        <div class="hidden items-center gap-1 md:flex">
            <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                Dashboard
            </x-nav-link>
            <x-nav-link :href="route('admin.enquiries.index')" :active="request()->routeIs('admin.enquiries.*')">
                Enquiries
            </x-nav-link>
            <x-nav-link :href="route('admin.feedback.index')" :active="request()->routeIs('admin.feedback.*')">
                Feedback
            </x-nav-link>
            <x-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                Users
            </x-nav-link>
            <x-nav-link :href="route('admin.training-matrix.index')" :active="request()->routeIs('admin.training-matrix.*')">
                Training Matrix
            </x-nav-link>
        </div>

        <div class="hidden items-center md:flex">
            <x-dropdown align="right" width="48">
                <x-slot name="trigger">
                    <button class="inline-flex items-center rounded-[10px] border border-white/20 bg-white/10 px-3 py-2 text-sm font-medium text-white transition hover:bg-white/15 focus:outline-none">
                        <div>{{ Auth::user()->name }}</div>
                        <div class="ms-2">
                            <svg class="h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </div>
                    </button>
                </x-slot>

                <x-slot name="content">
                    <x-dropdown-link :href="route('profile.edit')">
                        Profile
                    </x-dropdown-link>

                    <x-dropdown-link :href="route('admin.settings.edit')">
                        Integration settings
                    </x-dropdown-link>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-dropdown-link :href="route('logout')"
                                onclick="event.preventDefault(); this.closest('form').submit();">
                            Log Out
                        </x-dropdown-link>
                    </form>
                </x-slot>
            </x-dropdown>
        </div>

        <div class="flex items-center md:hidden">
            <button @click="open = ! open" class="inline-flex items-center justify-center rounded-md p-2 text-white/90 transition hover:bg-white/10 hover:text-white focus:outline-none">
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="relative z-10 hidden border-t border-white/15 md:hidden">
        <div class="space-y-1 px-4 py-3">
            <x-responsive-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                Dashboard
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('admin.enquiries.index')" :active="request()->routeIs('admin.enquiries.*')">
                Enquiries
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('admin.feedback.index')" :active="request()->routeIs('admin.feedback.*')">
                Feedback
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                Users
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('admin.training-matrix.index')" :active="request()->routeIs('admin.training-matrix.*')">
                Training Matrix
            </x-responsive-nav-link>
        </div>

        <div class="border-t border-white/15 px-4 py-4">
            <div class="text-sm font-medium text-white">{{ Auth::user()->name }}</div>
            <div class="text-xs text-white/80">{{ Auth::user()->email }}</div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    Profile
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('admin.settings.edit')" :active="request()->routeIs('admin.settings.*')">
                    Integration settings
                </x-responsive-nav-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault(); this.closest('form').submit();">
                        Log Out
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
