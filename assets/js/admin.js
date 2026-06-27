/* Media Optimizer — minimal JS (quality slider only, bulk is server-side) */
(function(){
  var slider = document.getElementById('mo-qslider');
  var label  = document.getElementById('mo-qval');
  if(slider && label){
    slider.addEventListener('input', function(){ label.textContent = this.value; });
  }
})();
