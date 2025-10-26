 <div wire:key="wire-key-card-{{ $item->ip }}"
     class="relative p-3 transition-all border border-gray-100 rounded-lg shadow group hover:shadow-md">

     {{-- 🧠 Header --}}
     <div class="flex items-center justify-between mb-2">
         <div>
             <h3 class="text-lg font-semibold text-gray-800">{{ $item->name ?? 'No Name' }}</h3>
             <div class="flex items-center gap-3 mt-0.5">
                 <span class="text-xs text-gray-500">{{ $item->ip ?? 'Unknown IP' }}</span>
                 <div class="flex items-center gap-1">
                     @if (is_null($powerLevel))
                         <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-5 h-5 text-red-500">
                             <path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                 stroke-linejoin="round" d="M2 2l20 20M6 18v-2m4 2v-4m4 4v-6m4 6V8" />
                         </svg>
                         <span class="text-xs font-semibold text-red-500">N/A</span>
                     @else
                         <div class="flex items-end gap-[2px]">
                             @for ($i = 1; $i <= 3; $i++)
                                 <div class="w-[4px] rounded-sm transition-all duration-200 {{ $i <= $powerLevel ? 'bg-green-500' : 'bg-gray-300' }}"
                                     style="height: {{ $i * 5 }}px"></div>
                             @endfor
                         </div>
                         <span class="text-xs font-semibold text-gray-800">
                             @if ($powerLevel === 3)
                                 ON
                             @elseif ($powerLevel === 0)
                                 OFF
                             @endif
                         </span>
                     @endif
                 </div>
             </div>
         </div>

         {{-- ⚡ Power (hover mới hiện) --}}
         <div x-data="{ open: false }"
             class="relative inline-block text-left transition-opacity duration-200 opacity-0 group-hover:opacity-100">
             <button @click="open = !open"
                 class="flex items-center gap-1 px-3 py-1.5 bg-gray-800 text-white text-sm rounded-md hover:bg-gray-700 transition">
                 ⚡ Power
                 <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 ml-1" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor">
                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                 </svg>
             </button>

             <div x-show="open" @click.outside="open=false" x-transition.origin-top
                 class="absolute right-0 z-20 w-32 mt-1 overflow-hidden bg-white border border-gray-200 rounded-md shadow-lg">
                 <button wire:click="powerAction('{{ $item->ip }}','on')"
                     class="flex items-center w-full gap-2 px-3 py-2 text-sm text-green-600 transition hover:bg-green-50">
                     <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor" stroke-width="2">
                         <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v9m0 9v-9m0 0h9m-9 0H3" />
                     </svg>
                     Power ON
                 </button>

                 <button wire:click="powerAction('{{ $item->ip }}','off')"
                     class="flex items-center w-full gap-2 px-3 py-2 text-sm text-red-600 transition hover:bg-red-50">
                     <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor" stroke-width="2">
                         <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                     </svg>
                     Power OFF
                 </button>

                 <button wire:click="powerAction('{{ $item->ip }}','reset')"
                     class="flex items-center w-full gap-2 px-3 py-2 text-sm text-yellow-600 transition hover:bg-yellow-50">
                     <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor" stroke-width="2">
                         <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6M20 20v-6h-6M4 20h16M20 4H4" />
                     </svg>
                     Reset
                 </button>
             </div>
         </div>
     </div>

     {{-- 🌡️ CPU & FAN --}}
     <div
         class="grid grid-cols-2 gap-4 p-2 rounded-lg shadow-sm transition-all duration-300 {{ $bgClass }} {{ $isInactive ? 'opacity-50 grayscale' : '' }}">

         {{-- Cột CPU --}}
         <div class="flex flex-col space-y-1 min-w-[120px]">
             <div class="flex items-center gap-2">
                 <div
                     class="w-3.5 h-3.5 rounded-full {{ $isInactive ? 'bg-gray-400' : $colorTempClass($t0) }} animate-pulse">
                 </div>
                 <span class="text-sm font-medium text-gray-900">CPU1:</span>
                 <span
                     class="text-sm font-medium tabular-nums">{{ $isInactive ? 'N/A' : ($t0 ? $t0 . '°C' : '--') }}</span>
             </div>
             <div class="flex items-center gap-2">
                 <div
                     class="w-3.5 h-3.5 rounded-full {{ $isInactive ? 'bg-gray-400' : $colorTempClass($t1) }} animate-pulse">
                 </div>
                 <span class="text-sm font-medium text-gray-900">CPU2:</span>
                 <span
                     class="text-sm font-medium tabular-nums">{{ $isInactive ? 'N/A' : ($t1 ? $t1 . '°C' : '--') }}</span>
             </div>
         </div>

         {{-- Cột FAN --}}
         <div class="flex flex-col space-y-1 min-w-[120px]">
             <div class="flex items-center gap-2">
                 <div class="w-3 h-3 rounded-full {{ $isInactive ? 'bg-gray-400' : $colorFan($f0) }}">
                 </div>
                 <span class="text-sm font-medium text-gray-900">FAN1:</span>
                 <span
                     class="text-sm font-medium tabular-nums">{{ $isInactive ? 'N/A' : ($f0 ? $f0 . 'RPM' : '--') }}</span>
             </div>
             <div class="flex items-center gap-2">
                 <div class="w-3 h-3 rounded-full {{ $isInactive ? 'bg-gray-400' : $colorFan($f1) }}">
                 </div>
                 <span class="text-sm font-medium text-gray-900">FAN2:</span>
                 <span
                     class="text-sm font-medium tabular-nums">{{ $isInactive ? 'N/A' : ($f1 ? $f1 . 'RPM' : '--') }}</span>
             </div>
         </div>
     </div>
 </div>
