<div class="space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Minha Assinatura</h1>
            <p class="text-gray-600 text-sm">Gerencie o plano da sua empresa na FreelancePro.</p>
        </div>
        <a href="<?= $this->url('plans') ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-purple-200 text-purple-700 text-sm hover:bg-purple-50 transition">
            <i data-lucide="layers" class="w-4 h-4"></i>
            Ver planos
        </a>
    </div>

    <?php if (empty($subscription)): ?>
        <div class="bg-white rounded-xl shadow-sm p-8 text-center">
            <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i data-lucide="credit-card" class="w-8 h-8 text-purple-600"></i>
            </div>
            <h2 class="text-lg font-semibold text-gray-800 mb-2">Nenhuma assinatura ativa</h2>
            <p class="text-gray-600 mb-4">Escolha um plano para começar a usar todos os recursos da plataforma.</p>
            <a href="<?= $this->url('plans') ?>" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-purple-600 text-white text-sm hover:bg-purple-700 transition">
                <i data-lucide="layers" class="w-4 h-4"></i>
                Escolher plano
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <p class="text-xs uppercase tracking-wide text-gray-500 mb-1">Status</p>
                <?php
                $status = $subscription['status'] ?? 'active';
                $labelMap = [
                    'active' => 'Ativa',
                    'pending' => 'Pendente',
                    'cancelled' => 'Cancelada',
                    'inactive' => 'Inativa',
                    'overdue' => 'Em atraso',
                ];
                $classMap = [
                    'active' => 'bg-green-100 text-green-700',
                    'pending' => 'bg-yellow-100 text-yellow-700',
                    'cancelled' => 'bg-red-100 text-red-700',
                    'inactive' => 'bg-gray-100 text-gray-700',
                    'overdue' => 'bg-orange-100 text-orange-700',
                ];
                $label = $labelMap[$status] ?? $status;
                $class = $classMap[$status] ?? 'bg-gray-100 text-gray-700';
                ?>
                <span class="inline-flex px-3 py-1 rounded-full text-xs font-medium <?= $class ?>"><?= $label ?></span>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6">
                <p class="text-xs uppercase tracking-wide text-gray-500 mb-1">Início do ciclo</p>
                <p class="text-lg font-semibold text-gray-800">
                    <?= !empty($subscription['current_period_start']) ? date('d/m/Y', strtotime($subscription['current_period_start'])) : '-' ?>
                </p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6">
                <p class="text-xs uppercase tracking-wide text-gray-500 mb-1">Fim do ciclo</p>
                <p class="text-lg font-semibold text-gray-800">
                    <?= !empty($subscription['current_period_end']) ? date('d/m/Y', strtotime($subscription['current_period_end'])) : '-' ?>
                </p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 flex items-center justify-between mt-4">
            <div>
                <p class="text-sm text-gray-600">Para alterar ou cancelar sua assinatura, entre em contato com o suporte ou use o botão abaixo (se configurado).</p>
            </div>
            <form method="POST" action="<?= $this->url('subscription/cancel') ?>" onsubmit="return confirm('Tem certeza que deseja cancelar sua assinatura?');">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-red-50 text-red-700 text-sm hover:bg-red-100 transition">
                    <i data-lucide="x-circle" class="w-4 h-4"></i>
                    Cancelar assinatura
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>
