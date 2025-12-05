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
                    <div id="recordControls" class="hidden mt-4 flex items-center justify-center gap-3">
                        <button type="button" id="pauseBtn" class="px-4 py-2 rounded-lg bg-yellow-500 text-white">Pausar</button>
                        <button type="button" id="resumeBtn" class="px-4 py-2 rounded-lg bg-green-600 text-white hidden">Continuar</button>
                        <button type="button" id="stopBtn" class="px-4 py-2 rounded-lg bg-gray-800 text-white">Finalizar</button>
                    </div>
                    <canvas id="waveCanvas" class="mx-auto mt-4" width="420" height="80"></canvas>
                    <div id="audioError" class="hidden text-red-600 text-sm mt-3"></div>
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
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const ct = response.headers.get('content-type') || '';
        let data = null, text = '';
        if (ct.indexOf('application/json') !== -1) {
            data = await response.json();
        } else {
            text = await response.text();
        }
        if (response.ok && data && data.success) {
            window.location.href = '<?= $this->url('playbooks/') ?>' + data.playbook_id;
        } else {
            const errMsg = (data && (data.error || data.message)) || text || 'Erro ao gerar playbook';
            alert(errMsg);
            form.classList.remove('hidden');
            loadingState.classList.add('hidden');
        }
    } catch (error) {
        alert('Erro ao processar requisição');
        form.classList.remove('hidden');
        loadingState.classList.add('hidden');
    }
});

(function(){
  var recordBtn=document.getElementById('recordBtn');
  var pauseBtn=document.getElementById('pauseBtn');
  var resumeBtn=document.getElementById('resumeBtn');
  var stopBtn=document.getElementById('stopBtn');
  var controls=document.getElementById('recordControls');
  var wave=document.getElementById('waveCanvas');
  var errorBox=document.getElementById('audioError');
  var ctx=wave?wave.getContext('2d'):null;
  var mediaRecorder=null; var chunks=[]; var audioCtx=null; var analyser=null; var source=null; var raf=0; var stream=null;
  function draw(){
    if(!analyser||!ctx) return; var w=wave.width, h=wave.height; var data=new Uint8Array(analyser.fftSize); analyser.getByteTimeDomainData(data); ctx.clearRect(0,0,w,h); ctx.fillStyle='#eef2ff'; ctx.fillRect(0,0,w,h); ctx.lineWidth=2; ctx.strokeStyle='#2563eb'; ctx.beginPath(); var slice=w/(data.length); var x=0; for(var i=0;i<data.length;i++){ var v=data[i]/128.0; var y=v*h/2; if(i===0){ ctx.moveTo(x,y);} else { ctx.lineTo(x,y);} x+=slice;} ctx.stroke(); raf=requestAnimationFrame(draw);
  }
  function stopVisual(){ if(raf) cancelAnimationFrame(raf); raf=0; if(audioCtx){ audioCtx.close().catch(function(){}); audioCtx=null; analyser=null; source=null; } }
  function showError(t){ if(errorBox){ errorBox.textContent=t; errorBox.classList.remove('hidden'); } }
  function clearError(){ if(errorBox){ errorBox.textContent=''; errorBox.classList.add('hidden'); } }
  async function start(){
    clearError();
    try{
      stream=await navigator.mediaDevices.getUserMedia({audio:true});
      mediaRecorder=new MediaRecorder(stream);
      chunks=[];
      mediaRecorder.ondataavailable=function(e){ if(e.data && e.data.size>0){ chunks.push(e.data);} };
      mediaRecorder.onstop=async function(){ try{ var blob=new Blob(chunks,{type:'audio/webm'}); var fd=new FormData(); fd.append('_token', document.querySelector('input[name="_token"]').value); fd.append('audio', blob, 'gravacao.webm'); var resp=await fetch('<?= $this->url('playbooks/transcribe') ?>',{method:'POST', body:fd}); var data=await resp.json(); if(data && data.success){ var ta=document.getElementById('content'); if(ta){ ta.value=(ta.value?ta.value+'\n\n':'')+data.text; } } else { showError((data&&data.error)||'Falha na transcrição'); } }catch(err){ showError('Erro ao enviar/transcrever'); } finally { if(stream){ stream.getTracks().forEach(function(t){t.stop();}); } stopVisual(); recordBtn.classList.remove('hidden'); controls.classList.add('hidden'); resumeBtn.classList.add('hidden'); pauseBtn.classList.remove('hidden'); } };
      mediaRecorder.start();
      audioCtx=new (window.AudioContext||window.webkitAudioContext)();
      analyser=audioCtx.createAnalyser(); analyser.fftSize=2048; source=audioCtx.createMediaStreamSource(stream); source.connect(analyser); draw();
      recordBtn.classList.add('hidden'); controls.classList.remove('hidden');
    }catch(e){ showError('Permita o acesso ao microfone para gravar.'); }
  }
  function pause(){ if(mediaRecorder && mediaRecorder.state==='recording'){ mediaRecorder.pause(); pauseBtn.classList.add('hidden'); resumeBtn.classList.remove('hidden'); } }
  function resume(){ if(mediaRecorder && mediaRecorder.state==='paused'){ mediaRecorder.resume(); resumeBtn.classList.add('hidden'); pauseBtn.classList.remove('hidden'); } }
  function stop(){ if(mediaRecorder && (mediaRecorder.state==='recording'||mediaRecorder.state==='paused')){ mediaRecorder.stop(); } }
  if(recordBtn){ recordBtn.addEventListener('click', start); }
  if(pauseBtn){ pauseBtn.addEventListener('click', pause); }
  if(resumeBtn){ resumeBtn.addEventListener('click', resume); }
  if(stopBtn){ stopBtn.addEventListener('click', stop); }
})();
</script>
