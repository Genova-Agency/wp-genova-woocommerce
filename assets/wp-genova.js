/**
 * Author:      Evans Wanguba
 * Text Domain: wp-genova.js
 */

jQuery(function($){
  function loadPlans(){
    $.post(WP_GENOVA.ajax_url, { action: 'wp_genova_get_plans', nonce: WP_GENOVA.nonce }, function(resp){
      if(resp.success){
        var plans = resp.data.plans;
        var $sel = $('#wp-genova-plan');
        $sel.find('option:not(:first)').remove();
        plans.forEach(function(p){
          $sel.append($('<option/>').attr('value', p.id).text(p.name + ' (AED ' + p.price + ')').data('price', p.price));
        });
      } else console.error(resp);
    });
  }
  loadPlans();
  $('#wp-genova-plan').on('change', function(){
    var planId = $(this).val();
    $.post(WP_GENOVA.ajax_url, { action: 'wp_genova_set_plan', nonce: WP_GENOVA.nonce, plan_id: planId }, function(resp){
      if(resp.success){
        if(resp.data && resp.data.plan) $('#wp-genova-selected').text(resp.data.plan.name + ' — AED ' + resp.data.plan.price);
        else $('#wp-genova-selected').text('');
        $(document.body).trigger('update_checkout');
      } else console.error(resp);
    });
  });
});