/*
          JS de suporte:
          - faz submit via fetch (AJAX) para apresentar mensagens sem reload.
          - se o servidor devolver {ok:true, redirect:"/painel"} redireciona.
          - mensagens em Português.
        */
        document.getElementById('loginForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const toast = document.getElementById('toast');
            toast.style.display = 'none';
            const user = document.getElementById('username').value.trim();
            const pass = document.getElementById('password').value;

            if (!user || !pass) {
                toast.textContent = 'Preencha o nome de utilizador e a palavra-passe.';
                toast.className = 'toast error';
                toast.style.display = 'block';
                return;
            }

            const btn = document.getElementById('btnLogin');
            const loading = document.getElementById('loading');
            btn.disabled = true; loading.style.display = 'block';

            try {
                const formData = new FormData();
                formData.append('username', user);
                formData.append('password', pass);

                const res = await fetch(this.action, { method: 'POST', body: formData, credentials: 'same-origin' });
                // tentar ler JSON
                const data = await res.json().catch(() => null);

                if (res.ok && data && data.ok) {
                    toast.textContent = data.mensagem || 'Autenticação bem sucedida — a abrir painel…';
                    toast.className = 'toast ok';
                    toast.style.display = 'block';
                    // se o servidor indicar redirect, segue
                    if (data.redirect) {
                        setTimeout(() => location.href = data.redirect, 700);
                    } else {
                        setTimeout(() => location.reload(), 800);
                    }
                } else {
                    // tratar mensagens em PT do servidor (erro)
                    const msg = data && (data.erro || data.mensagem) ? (data.erro || data.mensagem) : 'Credenciais inválidas.';
                    toast.textContent = msg;
                    toast.className = 'toast error';
                    toast.style.display = 'block';
                }
            } catch (err) {
                toast.textContent = 'Erro no servidor. Verifique a ligação.';
                toast.className = 'toast error';
                toast.style.display = 'block';
                console.error(err);
            } finally {
                btn.disabled = false;
                loading.style.display = 'none';
            }
        });