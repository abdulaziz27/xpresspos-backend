<div class="space-y-6">
    <!-- Controls -->
    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
        <div class="flex items-center space-x-4">
            <label class="flex items-center">
                <input type="checkbox" 
                       wire:model="useDefaultPermissions" 
                       wire:change="toggleUseDefault"
                       class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                <span class="ml-2 text-sm font-medium text-gray-700">Gunakan Permission Default Role</span>
            </label>
        </div>
        
        <button type="button" 
                wire:click="resetToDefault"
                class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            Reset ke Default
        </button>
    </div>

    <!-- Permission Matrix -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach($categories as $category => $categoryPermissions)
            <div class="border border-gray-200 rounded-lg overflow-hidden">
                <!-- Category Header -->
                <div class="bg-gray-100 px-4 py-3 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-medium text-gray-900 capitalize">
                            {{ ucfirst($category) }}
                        </h3>
                        <div class="flex space-x-2">
                            <button type="button" 
                                    wire:click="selectAllInCategory('{{ $category }}')"
                                    class="text-xs text-primary-600 hover:text-primary-800">
                                Pilih Semua
                            </button>
                            <span class="text-gray-300">|</span>
                            <button type="button" 
                                    wire:click="deselectAllInCategory('{{ $category }}')"
                                    class="text-xs text-gray-600 hover:text-gray-800">
                                Hapus Semua
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Permissions List -->
                <div class="p-4 space-y-3">
                    @foreach($categoryPermissions as $permission => $label)
                        <label class="flex items-center">
                            <input type="checkbox" 
                                   wire:model="permissions.{{ $category }}"
                                   value="{{ $permission }}"
                                   @if($useDefaultPermissions) disabled @endif
                                   class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-500 focus:ring-primary-500 @if($useDefaultPermissions) opacity-50 @endif">
                            <span class="ml-3 text-sm text-gray-700 @if($useDefaultPermissions) opacity-50 @endif">
                                {{ $label }}
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <!-- Summary -->
    <div class="p-4 bg-blue-50 rounded-lg">
        <h4 class="text-sm font-medium text-blue-900 mb-2">Ringkasan Permissions</h4>
        <div class="text-sm text-blue-700">
            @php
                $totalSelected = collect($permissions)->flatten()->count();
            @endphp
            
            @if($useDefaultPermissions)
                <p>Menggunakan {{ $totalSelected }} permission default untuk role <strong>{{ $selectedRole }}</strong></p>
            @else
                <p>{{ $totalSelected }} permission kustom dipilih</p>
            @endif
            
            @if($totalSelected > 0)
                <div class="mt-2 flex flex-wrap gap-1">
                    @foreach($permissions as $category => $categoryPerms)
                        @if(count($categoryPerms) > 0)
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                {{ ucfirst($category) }}: {{ count($categoryPerms) }}
                            </span>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <!-- Hidden inputs for form submission -->
    @foreach($permissions as $category => $categoryPerms)
        @foreach($categoryPerms as $permission)
            <input type="hidden" name="permissions[{{ $category }}][]" value="{{ $permission }}">
        @endforeach
    @endforeach
</div>

<script>
    // Listen for role changes from parent form
    document.addEventListener('livewire:load', function () {
        Livewire.on('roleChanged', role => {
            @this.handleRoleChange(role);
        });
    });
</script>