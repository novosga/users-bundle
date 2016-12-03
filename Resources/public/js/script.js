/**
 * Novo SGA - Usuarios
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
(function () {
    'use strict'
    
    var applyViewCargoEvent = function () {
        var className = 'btn-view-cargo';
        
        $('#lotacoes .' + className).each(function (i, e) {
            $(e)
                .on('click', function(e) {
                    e.preventDefault();
                    var id = $(this).parent().parent().find('.cargo').val();
                    dialogCargo.viewCargo(id);
                })
                .removeClass(className);
        });
    };
    
    $('[data-prototype]').collection({
        onadd: function (evt) {
            applyViewCargoEvent();
        }
    });
    
    $('#lotacoes-view .btn-view-cargo').on('click', function (e) {
        e.preventDefault();
        var id = $(this).data('id');
        dialogCargo.viewCargo(id);
    });
    
    applyViewCargoEvent();
    
    var dialogCargo = new Vue({
        el: '#dialog-cargo',
        data: {
            cargo: null
        },
        methods: {
            viewCargo: function(cargoId) {
                var self = this;
                App.ajax({
                    url: App.url('/novosga.users/cargos/') + cargoId,
                    success: function(response) {
                        self.cargo = response.data;
                        $('#dialog-cargo').modal('show')
                    }
                });
            },

            alterarSenha: function() {
                App.ajax({
                    url: App.url('alterar_senha'),
                    type: 'post',
                    data: {
                        id: $('#senha_id').val(),
                        senha: $('#senha_senha').val(), 
                        confirmacao: $('#senha_confirmacao').val()
                    },
                    success: function() {
                        $('#senha_senha').val('');
                        $('#senha_confirmacao').val('');
                        alert(App.Usuarios.labelSenhaAlterada);
                        $('#dialog-senha').modal('hide');
                    },
                    error: function() {
                        $('#senha_senha').val('');
                        $('#senha_confirmacao').val('');
                    }
                });
            }
        }
    });
})();