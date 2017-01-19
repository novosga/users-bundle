/**
 * Novo SGA - Usuarios
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
(function () {
    'use strict'
    
    $('#lotacoes .unidade').each(function () {
        var el = $(this),
            id = parseInt(el.val());
        if (unidades.indexOf(id) === -1) {
            el.parent().parent().find('.btn-remove').hide();
        }
    });
    
    var applyViewPerfilEvent = function () {
        var className = 'btn-view-perfil';
        
        $('select.unidade option').each(function () {
            var el = $(this),
                id = parseInt(el.attr('value'));
            if (!isNaN(id) && unidades.indexOf(id) === -1) {
                el.remove();
            }
        });
        
        $('#lotacoes .' + className).each(function () {
            $(this)
                .on('click', function(e) {
                    e.preventDefault();
                    var id = $(this).parent().parent().find('.perfil').val();
                    dialogPerfil.viewPerfil(id);
                })
                .removeClass(className);
        });
    };
    
    $('[data-prototype]').collection({
        onadd: function (evt) {
            applyViewPerfilEvent();
        }
    });
    
    applyViewPerfilEvent();
        
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
                    success: function(response) {
                        self.perfil = response.data;
                        $('#dialog-perfil').modal('show');
                    }
                });
            },
            
            alterarSenha: function() {
                App.ajax({
                    url: App.url('/novosga.users/alterar_senha'),
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