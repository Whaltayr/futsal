  
        /*
          Script mínimo para navegação do painel.
          - troca painéis com base no botão da sidebar (data-target)
          - define hooks para botões rápidos
          - você liga este script às suas APIs (fetch) quando implementar o backend
        */
        (function () {
            const navButtons = document.querySelectorAll('.nav button');
            const panels = document.querySelectorAll('[data-role="panel"]');
            const pageTitle = document.getElementById('pageTitle');
            const pageSub = document.getElementById('pageSub');

            function show(target) {
                panels.forEach(p => p.style.display = (p.id === 'panel-' + target) ? '' : 'none');
                navButtons.forEach(b => b.classList.toggle('active', b.dataset.target === target));
                pageTitle.textContent = target === 'dashboard' ? 'Painel' : (target.charAt(0).toUpperCase() + target.slice(1));
                pageSub.textContent = 'Gestão — ' + pageTitle.textContent;
            }

            navButtons.forEach(b => {
                b.addEventListener('click', () => show(b.dataset.target));
            });

            // shortcuts
            document.querySelectorAll('[data-action]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const act = btn.dataset.action;
                    if (act === 'resolve-knockouts') {
                        if (!confirm('Gerar knockouts agora? Verifique que os resultados da fase de grupos estão corretos.')) return;
                        // chamada de exemplo (substituir por fetch real). Em produção use sessão.
                        fetch('/api/resolve_knockouts.php?admin_key=changeme_replace_with_real_auth')
                            .then(r => r.json())
                            .then(j => { alert(j.mensagem || 'Concluído'); console.log(j); })
                            .catch(err => { console.error(err); alert('Erro ao gerar knockouts'); });
                    } else if (act === 'new-team') {
                        show('teams');
                        // focar no formulário
                        setTimeout(() => document.querySelector('#formTeam input[name="name"]').focus(), 150);
                    } else if (act === 'new-tournament') {
                        show('tournaments');
                    } else if (act === 'new-fixture') {
                        show('fixtures');
                    }
                });
            });

          

            // default view
            show('dashboard');
        })();
    