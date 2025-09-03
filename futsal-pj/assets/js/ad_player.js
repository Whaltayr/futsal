/* Position select -> sincroniza com hidden 'position' antes do submit */
(function(){
  const form = document.getElementById('formPlayer');
  const sel = document.getElementById('position_select');
  const otherWrap = document.getElementById('position_other_wrap');
  const other = document.getElementById('position_other');
  const hidden = document.getElementById('position_hidden');

  // mostrar campo "Outra" quando selecionado
  sel.addEventListener('change', function(){
    if (this.value === 'Outra') {
      otherWrap.style.display = '';
      other.focus();
    } else {
      otherWrap.style.display = 'none';
      other.value = '';
    }
  });

  // no submit, escreve no hidden o valor escolhido/introduzido
  form.addEventListener('submit', function(e){
    // antes do código AJAX original, sincroniza position
    const val = sel.value === 'Outra' ? (other.value || '') : (sel.value || '');
    hidden.value = val.trim();
    // continua com o envio AJAX existente (o restante script fica inalterado)
  }, true); // capturar antes do handler existente para garantir sincronização
})();