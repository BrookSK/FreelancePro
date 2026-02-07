<div class="max-w-3xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm p-8">
        <h2 class="text-xl font-bold text-gray-800 mb-6">Criar Novo Playbook com IA</h2>
        
        <form id="playbookForm" class="space-y-6">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Título do Playbook</label>
                <input type="text" name="title" id="title" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       placeholder="Ex: Procedimentos de Atendimento ao Cliente">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fonte do conteúdo</label>
                <select name="source_type" id="sourceType" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="text">Texto digitado</option>
                    <option value="file">Upload de arquivo (PDF, DOC, TXT)</option>
                    <option value="audio">Áudio (transcrição automática)</option>
                </select>
            </div>
            
            <div id="textInput">
                <label class="block text-sm font-medium text-gray-700 mb-1">Conteúdo base</label>
                <textarea name="content" id="content" rows="8"
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                          placeholder="Digite ou cole aqui o conteúdo base para a IA gerar o playbook..."></textarea>
                <p class="text-sm text-gray-500 mt-1">A IA vai estruturar este conteúdo em um playbook completo com questionário.</p>
            </div>
            
            <div id="fileInput" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-1">Upload de arquivo</label>
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
                    <input type="file" name="file" id="fileUpload" accept=".pdf,.doc,.docx,.txt" class="hidden">
                    <label for="fileUpload" class="cursor-pointer">
                        <i data-lucide="upload" class="w-12 h-12 text-gray-400 mx-auto mb-4"></i>
                        <p class="text-gray-600">Clique para selecionar ou arraste o arquivo</p>
                        <p class="text-sm text-gray-500 mt-1">PDF, DOC, DOCX ou TXT (máx. 10MB)</p>
                    </label>
                </div>
            </div>
            
            <div id="audioInput" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-1">Gravação de áudio</label>
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
                    <button type="button" id="recordBtn" class="bg-red-500 hover:bg-red-600 text-white px-6 py-3 rounded-full flex items-center gap-2 mx-auto transition">
                        <i data-lucide="mic" class="w-5 h-5"></i>
                        <span>Iniciar Gravação</span>
                    </button>
                    <p class="text-sm text-gray-500 mt-4">Ou faça upload de um arquivo de áudio (MP3, WAV)</p>
                    <input type="file" name="audio" accept=".mp3,.wav,.ogg" class="mt-2">
                </div>
            </div>
            
            <div class="flex items-center gap-4 pt-4">
                <button type="submit" id="generateBtn" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg font-semibold flex items-center justify-center gap-2 transition">
                    <i data-lucide="sparkles" class="w-5 h-5"></i>
                    <span>Gerar Playbook com IA</span>
                </button>
                <a href="<?= $this->url('playbooks') ?>" class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                    Cancelar
                </a>
            </div>
        </form>
        
        <!-- Loading State -->
        <div id="loadingState" class="hidden text-center py-12">
            <div class="animate-spin w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full mx-auto mb-4"></div>
            <p class="text-gray-600">Gerando playbook com IA...</p>
            <p class="text-sm text-gray-500">Isso pode levar alguns segundos</p>
        </div>
    </div>
</div>

<script>
document.getElementById('sourceType').addEventListener('change', function() {
    document.getElementById('textInput').classList.add('hidden');
    document.getElementById('fileInput').classList.add('hidden');
    document.getElementById('audioInput').classList.add('hidden');
    
    document.getElementById(this.value + 'Input').classList.remove('hidden');
});

document.getElementById('playbookForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const form = this;
    const loadingState = document.getElementById('loadingState');
    
    form.classList.add('hidden');
    loadingState.classList.remove('hidden');
    
    try {
        const formData = new FormData(form);
        const response = await fetch('<?= $this->url('playbooks/generate') ?>', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            window.location.href = '<?= $this->url('playbooks/') ?>' + data.playbook_id;
        } else {
            alert(data.error || 'Erro ao gerar playbook');
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
