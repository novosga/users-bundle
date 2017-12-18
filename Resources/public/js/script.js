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
        }
    });
        
    var lotacoesTable = new Vue({
        el: '#lotacoes',
        data: {
            lotacoes: lotacoes,
            lotacoesRemovidas: lotacoesRemovidas
        },
        computed: {
            idsLotacoesRemovidas: function () {
                return this.lotacoesRemovidas.map(function (lotacao) {
                    return lotacao.id;
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
                if (lotacao.id) {
                    this.lotacoesRemovidas.push(lotacao);
                }
            },
            viewPerfil: function (id) {
                dialogPerfil.viewPerfil(id);
            },
        }
    });
    
    new Vue({
        el: '#dialog-senha',
        data: {
            errors: {}
        },
        methods: {
            alterarSenha: function (e) {
                var $elem = $(e.target), self = this;
                
                self.errors = {};
                $.ajax({
                    url: $elem.attr('action'),
                    type: $elem.attr('method'),
                    data: $elem.serialize(),
                    success: function (response) {
                        if (!response.data.error) {
                            $elem.trigger('reset');
                            $('#dialog-senha').modal('hide');
                        } else {
                            self.errors = response.data.errors ? response.data.errors : {};
                        }
                    },
                });
            }
        }
    });
    
    $('#dialog-lotacao').on('show.bs.modal', function () {
        var ids = lotacoesTable.lotacoes.map(function(lotacao) { 
            return lotacao.id; 
        });
        
        $(this)
            .find('.modal-body')
            .load(App.url('/novosga.users/novalotacao?ignore=') + ids.join(','));
    });
    
    $('#lotacao-form').on('submit', function(e) {
        e.preventDefault();
        
        var perfil  = $('#lotacao_perfil :selected'),
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
        
        $('#dialog-lotacao').modal('hide');
    });
})();