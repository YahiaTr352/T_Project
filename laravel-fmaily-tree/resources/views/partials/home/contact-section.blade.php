{{-- ================================================================
HOMEPAGE CONTACT / SUGGESTION WIDGET (Matching Mobile App HomeContactWidget)
================================================================ --}}
<section class="py-4 md:py-6">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8 max-w-7xl">
        <a href="{{ route('support.index') }}#contact-form"
            class="group block bg-white hover:bg-emerald-50/30 rounded-2xl md:rounded-3xl p-4 sm:p-5 border border-slate-200/90 hover:border-emerald-300 shadow-sm hover:shadow-md transition-all duration-300"
            aria-label="تواصل مع إدارة العائلة وتقديم الاقتراحات">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-6">
                {{-- Right Side (RTL): Suggestion Icon + Text --}}
                <div class="flex items-center gap-3.5 sm:gap-4 flex-1 min-w-0">
                    {{-- Bulb Icon --}}
                    <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-600 flex-shrink-0 group-hover:scale-105 transition-transform duration-300 shadow-inner">
                        <i class="far fa-lightbulb text-lg sm:text-xl"></i>
                    </div>

                    {{-- Text Info --}}
                    <div class="min-w-0 flex-1">
                        <h3 class="font-bold text-slate-900 text-sm sm:text-base leading-snug group-hover:text-emerald-800 transition-colors">
                            هل لديك اقتراح؟
                        </h3>
                        <p class="text-xs sm:text-sm text-slate-500 truncate leading-relaxed">
                            شاركنا أفكارك وملاحظاتك لتطوير المنصة
                        </p>
                    </div>
                </div>

                {{-- Left Side (RTL): Action Button --}}
                <div class="flex-shrink-0 self-end sm:self-auto">
                    <span class="inline-flex items-center gap-2 px-4 py-2 sm:py-2.5 rounded-xl bg-emerald-700 group-hover:bg-emerald-800 text-white font-bold text-xs sm:text-sm shadow-sm transition-all duration-200">
                        <span>شاركنا باقتراحك</span>
                        <i class="fas fa-chevron-left text-[11px] sm:text-xs transition-transform group-hover:-translate-x-1"></i>
                    </span>
                </div>
            </div>
        </a>
    </div>
</section>
