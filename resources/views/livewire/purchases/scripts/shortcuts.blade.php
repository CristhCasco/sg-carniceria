<script>
	var listener = new window.keypress.Listener();

	listener.simple_combo("f1", function(){
		document.getElementById('code').value =''
		document.getElementById('code').focus()
	})

	listener.simple_combo("f2", function() {
		document.getElementById('cash').value =''
		document.getElementById('cash').focus()
		document.getElementById('hiddenTotal').value =''
	})

	listener.simple_combo("f3", function() {
		console.log('f3')
		@this.savePurchase()
	})

	listener.simple_combo("f4", function() {
		var total = parseFloat(document.getElementById('hiddenTotal').value)		
		if(total > 0) {
			Confirm(0, 'clearCart', '¿SEGUR@ DE ELIMINAR EL CARRITO?')
		} else 
		{
			noty('AGREGA PRODUCTOS A LA COMPRA')
		}
	})
	
	listener.simple_combo("f6", function() {
		console.log('print last : f10')
		livewire.emit('print-last')
	})

	



</script>