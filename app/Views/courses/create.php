<div class="max-w-3xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm p-8">
        <h2 class="text-xl font-bold text-gray-800 mb-6">Criar Novo Curso com IA</h2>
        
        <form id="courseForm" class="space-y-6">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Título do Curso</label>
                <input type="text" name="title" id="title" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                       placeholder="Ex: Fundamentos de Atendimento ao Cliente">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                <textarea name="description" id="description" rows="3" required
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                          placeholder="Descreva o objetivo e público-alvo do curso..."></textarea>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Conteúdo base (opcional)</label>
                <textarea name="content" id="content" rows="5"
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                          placeholder="Adicione tópicos, referências ou conteúdo que a IA deve considerar ao criar o curso..."></textarea>
                <p class="text-sm text-gray-500 mt-1">A IA criará 4 módulos com 3 aulas cada, incluindo conteúdo completo.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Vídeos das aulas</label>
                <p class="text-xs text-gray-500 mb-2">Escolha como os vídeos das aulas serão definidos.</p>
                <div class="space-y-2 text-sm text-gray-700">
                    <label class="flex items-start gap-2">
                        <input type="radio" name="video_source" value="ai" class="mt-1" checked>
                        <span>
                            <span class="font-medium">Sugestão da IA (padrão)</span><br>
                            A IA irá sugerir automaticamente um vídeo público do YouTube relacionado para cada aula.
                        </span>
                    </label>
                    <label class="flex items-start gap-2">
                        <input type="radio" name="video_source" value="upload" class="mt-1">
                        <span>
                            <span class="font-medium">Vou subir meus próprios vídeos</span><br>
                            As aulas serão criadas sem vídeo e você poderá fazer upload de um arquivo para cada aula depois.
                        </span>
                    </label>
                    <label class="flex items-start gap-2">
                        <input type="radio" name="video_source" value="manual" class="mt-1">
                        <span>
                            <span class="font-medium">Vou informar links manualmente</span><br>
                            As aulas serão criadas sem vídeo e você poderá configurar manualmente links do YouTube ou outros players.
                        </span>
                    </label>
                </div>
            </div>
            
            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                <p class="text-sm text-purple-800">
                    <strong>O curso incluirá:</strong> 4 módulos, 12 aulas com conteúdo completo, estrutura progressiva de aprendizado.
                </p>
            </div>
            
            <div class="flex items-center gap-4 pt-4">
                <button type="submit" id="generateBtn" class="flex-1 bg-purple-600 hover:bg-purple-700 text-white py-3 rounded-lg font-semibold flex items-center justify-center gap-2 transition">
                    <i data-lucide="sparkles" class="w-5 h-5"></i>
                    <span>Gerar Curso com IA</span>
                </button>
                <a href="<?= $this->url('courses') ?>" class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                    Cancelar
                </a>
            </div>
        </form>
        
        <!-- Loading State -->
        <div id="loadingState" class="hidden text-center py-12">
            <div class="animate-spin w-12 h-12 border-4 border-purple-600 border-t-transparent rounded-full mx-auto mb-4"></div>
            <p class="text-gray-600">Gerando curso completo com IA...</p>
            <p class="text-sm text-gray-500">Isso pode levar alguns minutos</p>
        </div>
    </div>
</div>

<script>
document.getElementById('courseForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const form = this;
    const loadingState = document.getElementById('loadingState');
    
    form.classList.add('hidden');
    loadingState.classList.remove('hidden');
    
    try {
        const formData = new FormData(form);
        const response = await fetch('<?= $this->url('courses/generate') ?>', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            window.location.href = '<?= $this->url('courses/') ?>' + data.course_id;
        } else {
            alert(data.error || 'Erro ao gerar curso');
            form.classList.remove('hidden');
            loadingState.classList.add('hidden');
        }
    } catch (error) {
        alert('Erro ao processar requisição');
        form.classList.remove('hidden');
        loadingState.classList.add('hidden');
    }
});
</script>
