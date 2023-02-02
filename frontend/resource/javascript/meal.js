$(function(){
		
	$('.J_SubmitMealOrder').click(function(){
		specs = []; check = true;
		$('input[name="specs[]"]').each(function(index, element) {
				
			// 如果不相等，说明规格没有选择完整，比如存在2个规格的情况下，只选中了一个规格
			if($(this).parent().find('.handle').children().length != $(this).parent().find('.handle').children().find('li.solid').length) {
				alert(lang.select_specs);
				check = false;
				return false;
			}
			specs.push({spec_id: $(this).val()});
		});
		if(check == true) {
			location.href = url(['order/meal', {id:$(this).attr('meal_id'), specs:JSON.stringify(specs)}]);
		}		
	});
});
	
function selectSpec(num, liObj ,specQty ,goods_id)
{
	$(liObj).attr("class", "solid");
	$(liObj).siblings(".solid").attr("class", "dotted");
	
	// 兼容有规格图片功能
	if(num == 1)
	{
		if($(liObj).find('img').length > 0)
		{
			$(liObj).parents('.goodsbox').find(".big_pic a img").attr('src',$(liObj).find('img').attr('src'));
		}
		else
		{
			$(liObj).parents('.goodsbox').find(".big_pic a img").attr('src',$(".tiny-pics ul li:first").find('img').attr('src'));
		}
	}
	
	// 当有2种规格并且选中了第一个规格时，刷新第二个规格
	if (num == 1 && specQty == 2)
	{
		$(liObj).parent().siblings('ul').children('.solid').attr('class','dotted');
	}
	else
	{
		var spec_2 = $(liObj).find('span').html(); //选择当前属性项
		var spec_1 = $(liObj).parent().siblings('ul').children('.solid').find('span').html();
		getSpecs(num,liObj,specQty,goods_id,spec_1,spec_2);
	}
}
function getSpecs(num,liObj,specQty,goods_id,spec_1,spec_2)
{
	$.getJSON(url(['meal/specinfo']), {num:num,specQty:specQty,goods_id:goods_id,spec_1:spec_1,spec_2:spec_2}, function(data){
		if (data.done) { 
			
			$(liObj).parent().parent().parent().find('.J_SpecPrice').html(price_format(data.retval.spec.price));
			$(liObj).parent().parent().parent().find('.J_SpecPrice').attr('price',data.retval.spec.price);
			$(liObj).parent().parent().siblings("input[name='specs[]']").val(data.retval.spec.spec_id);
			
			var sprice = 0;
			$('.J_SpecPrice').each(function(index, element) {
				sprice += parseFloat($(this).attr('price'));
			});
		}
		else
		{
			alert(data.msg);
		}
	});
}