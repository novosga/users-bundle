/**
 * Novo SGA - Usuarios
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
(function () {
    'use strict'
    
    var dialogPerfil = new Vue({
        el: '#dialog-perfil',
        data: {
            perfil: null
        },
        methods: {
            viewPerfil: function (id) {
                var self = this;
                App.ajax({
                    url: App.url('/novosga.users/perfis/') + id,
                    success: function (response) {
                        self.perfil = response.data;
                        $('#dialog-perfil').modal('show');
                    }
                });
            },
            
            alterarSenha: function () {
                App.ajax({
                    url: App.url('/novosga.users/password/'),
                    type: 'post',
                    data: {
                        id: $('#senha_id').val(),
                        senha: $('#senha_senha').val(),
                        confirmacao: $('#senha_confirmacao').val()
                    },
                    success: function () {
                        $('#senha_senha').val('');
                        $('#senha_confirmacao').val('');
                        alert(App.Usuarios.labelSenhaAlterada);
                        $('#dialog-senha').modal('hide');
                    },
                    error: function () {
                        $('#senha_senha').val('');
                        $('#senha_confirmacao').val('');
                    }
                });
            }
        }
    });
        
    var lotacoesTable = new Vue({
        el: '#lotacoes',
        data: {
            lotacoes: lotacoes,
            lotacoesRemovidas: []
        },
        computed: {
            unidadesRemovidas: function () {
                return this.lotacoesRemovidas.map(function (lotacao) {
                    return lotacao.unidade.id;
                }).join(',');
            }
        },
        methods: {
            add: function (lotacao) {
                lotacao.novo = true;
                this.lotacoes.push(lotacao);
            },
            remove: function (lotacao) {
                this.lotacoes.splice(this.lotacoes.indexOf(lotacao), 1);
                this.lotacoesRemovidas.push(lotacao);
            },
            viewPerfil: function (id) {
                dialogPerfil.viewPerfil(id);
            },
        }
    });
    
    $('#dialog-lotacao').on('show.bs.modal', function () {
        var ids = lotacoesTable.lotacoes.map(function(lotacao) { 
            return lotacao.unidade.id; 
        });
        
        $(this)
                .find('.modal-body')
                .load(App.url('/novosga.users/novalotacao?ignore=') + ids.join(','));
    });
    
    $('#lotacao-form').on('submit', function(e) {
        e.preventDefault();
        
        var perfil = $('#lotacao_perfil :selected'),
            unidade = $('#lotacao_unidade :selected');
            
        if (perfil.val() && unidade.val()) {
            lotacoesTable.add({
                unidade: {
                    id: unidade.val(),
                    nome: unidade.text(),
                },
                perfil: {
                    id: perfil.val(),
                    nome: perfil.text(),
                }
            });
        }
        
        $('#dialog-lotacao').hide();
    });
})();