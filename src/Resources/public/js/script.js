/**
 * Novo SGA - Usuarios
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
(function () {
    'use strict'

    new Vue({
        el: '#users-form',
        data: {
            perfil: null,
            lotacaoModal: null,
            perfilModal: null,
            senhaModal: null,
            lotacoes: lotacoes,
            lotacoesRemovidas: lotacoesRemovidas,
            errors: {},
        },
        computed: {
            idsLotacoesRemovidas() {
                return this.lotacoesRemovidas.map((lotacao) => lotacao.id).join(',');
            }
        },
        methods: {
            viewPerfil(id) {
                App.ajax({
                    url: App.url('/novosga.users/perfis/') + id,
                    success: (response) => {
                        this.perfil = response.data;
                        this.perfilModal.show();
                    }
                });
            },
            add(lotacao) {
                lotacao.novo = true;
                this.lotacoes.push(lotacao);
            },
            remove(lotacao) {
                this.lotacoes.splice(this.lotacoes.indexOf(lotacao), 1);
                if (lotacao.id) {
                    this.lotacoesRemovidas.push(lotacao);
                }
            },
            async alterarSenha(e) {
                this.errors = {};
                const form = e.target
                const resp = await fetch(form.action, {
                    method: form.method || 'post',
                    body: new FormData(form)
                });
                const result = await resp.json();
                if (!result.data.error) {
                    form.reset();
                    this.senhaModal.hide();
                } else {
                    this.errors = result.data.errors || {};
                }
            },
            handleLotacaoSubmit() {
                const perfil = document.getElementById('lotacao_perfil');
                const unidade = document.getElementById('lotacao_unidade');

                if (perfil && unidade) {
                    this.add({
                        unidade: {
                            id: unidade.value,
                            nome: unidade.innerText,
                        },
                        perfil: {
                            id: perfil.value,
                            nome: perfil.innerText,
                        }
                    });
                }

                this.lotacaoModal.hide();
            },
            showSenhaModal() {
                this.senhaModal.show();
            },
            async showLotacaoModal() {
                const ids = this.lotacoes.map((lotacao) => lotacao.unidade.id);
                const resp = await fetch(App.url('/novosga.users/novalotacao?ignore=') + ids.join(','));
                const text = await resp.text();
                this.$refs.lotacaoModal.querySelector('.modal-body').innerHTML = text;

                this.lotacaoModal.show();
            }
        },
        mounted() {
            this.lotacaoModal = new bootstrap.Modal(this.$refs.lotacaoModal);
            this.perfilModal = new bootstrap.Modal(this.$refs.perfilModal);
            if (this.$refs.senhaModal) {
                this.senhaModal = new bootstrap.Modal(this.$refs.senhaModal);
            }
        }
    });
})();
