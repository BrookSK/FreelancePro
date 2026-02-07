<div class="max-w-4xl mx-auto">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Form -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm p-8">
                <h2 class="text-xl font-bold text-gray-800 mb-6">Dados para Pagamento</h2>
                
                <form id="checkoutForm" class="space-y-6">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                    <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                    
                    <!-- Personal Info -->
                    <div>
                        <h3 class="font-semibold text-gray-700 mb-4">Dados Pessoais</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nome Completo</label>
                                <input type="text" name="name" value="<?= htmlspecialchars($userData['name'] ?? '') ?>" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($userData['email'] ?? '') ?>" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                                <input type="text" name="cpf" value="<?= htmlspecialchars($userData['cpf'] ?? '') ?>" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                       placeholder="000.000.000-00">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Telefone</label>
                                <input type="tel" name="phone" value="<?= htmlspecialchars($userData['phone'] ?? '') ?>" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                       placeholder="(00) 00000-0000">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Address -->
                    <div>
                        <h3 class="font-semibold text-gray-700 mb-4">Endereço</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">CEP</label>
                                <input type="text" name="zip_code" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                       placeholder="00000-000">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Rua</label>
                                <input type="text" name="street" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Número</label>
                                <input type="text" name="number" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Complemento</label>
                                <input type="text" name="complement"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Bairro</label>
                                <input type="text" name="neighborhood" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Card Info -->
                    <div>
                        <h3 class="font-semibold text-gray-700 mb-4">Dados do Cartão</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nome no Cartão</label>
                                <input type="text" name="card_holder" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                       placeholder="Como está no cartão">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Número do Cartão</label>
                                <input type="text" name="card_number" required maxlength="19"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                       placeholder="0000 0000 0000 0000">
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Mês</label>
                                    <input type="text" name="card_month" required maxlength="2"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                           placeholder="MM">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Ano</label>
                                    <input type="text" name="card_year" required maxlength="4"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                           placeholder="AAAA">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">CVV</label>
                                    <input type="text" name="card_cvv" required maxlength="4"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                           placeholder="000">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" id="submitBtn" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-4 rounded-lg font-semibold transition flex items-center justify-center gap-2">
                        <i data-lucide="lock" class="w-5 h-5"></i>
                        Finalizar Assinatura
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Summary -->
        <div>
            <div class="bg-white rounded-xl shadow-sm p-6 sticky top-8">
                <h3 class="font-semibold text-gray-800 mb-4">Resumo</h3>
                
                <div class="border-b pb-4 mb-4">
                    <p class="text-lg font-bold text-gray-800"><?= htmlspecialchars($plan['name']) ?></p>
                    <p class="text-sm text-gray-500">Assinatura mensal</p>
                </div>
                
                <div class="flex justify-between items-center text-lg">
                    <span class="text-gray-600">Total</span>
                    <span class="font-bold text-gray-800">R$ <?= number_format($plan['price'], 2, ',', '.') ?>/mês</span>
                </div>
                
                <p class="text-xs text-gray-500 mt-4">
                    Ao assinar você concorda com nossos termos de uso. A cobrança será realizada mensalmente.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('checkoutForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="animate-spin">⏳</span> Processando...';
    
    try {
        const formData = new FormData(this);
        const response = await fetch('<?= $this->url('checkout') ?>', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            window.location.href = data.redirect;
        } else {
            alert(data.error || 'Erro ao processar pagamento');
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="lock" class="w-5 h-5"></i> Finalizar Assinatura';
        }
    } catch (error) {
        alert('Erro ao processar requisição');
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="lock" class="w-5 h-5"></i> Finalizar Assinatura';
    }
});
</script>
