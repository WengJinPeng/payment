<?php
include("../includes/common.php");
if($islogin2==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
$title='批量代付';
include './head.php';

// Check if batch transfer is enabled
if(!$conf['user_transfer']) showmsg('未开启代付功能');

// Calculate available balance
if($conf['settle_type']==1){
    $today=date("Y-m-d").' 00:00:00';
    $order_today=$DB->getColumn("SELECT SUM(realmoney) from pre_order where uid={$uid} and tid<>2 and status=1 and endtime>='$today'");
    if(!$order_today) $order_today = 0;
    $enable_money=round($userrow['money']-$order_today,2);
    if($enable_money<0)$enable_money=0;
}else{
    $enable_money=$userrow['money'];
}
if(!$conf['transfer_rate'])$conf['transfer_rate'] = $conf['settle_rate'];

// Get batch history
$batchList = $DB->getAll("SELECT * FROM pre_transfer_batch WHERE uid='$uid' ORDER BY id DESC LIMIT 10");
if(!$batchList) $batchList = array(); // Ensure it's an array if query returns false
?>

<div id="content" class="app-content" role="main">
    <div class="app-content-body ">
        <div class="bg-light lter b-b wrapper-md hidden-print">
            <h1 class="m-n font-thin h3">批量代付</h1>
        </div>
        <div class="wrapper-md control">
            <div class="row">
                <div class="col-sm-12">
                    <div class="panel panel-default">
                        <div class="panel-heading font-bold">
                            批量代付
                        </div>
                        <div class="panel-body">
                            <form id="batch-upload-form" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label>付款方式</label>
                                    <select name="app" class="form-control">
                                        <?php if($conf['transfer_alipay']>0){?><option value="alipay">支付宝</option><?php }?>
                                        <?php if($conf['transfer_wxpay']>0){?><option value="wxpay">微信</option><?php }?>
                                        <?php if($conf['transfer_qqpay']>0){?><option value="qqpay">QQ钱包</option><?php }?>
                                        <?php if($conf['transfer_bank']>0){?><option value="bank">银行卡</option><?php }?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>当前可用余额</label>
                                    <div class="input-group">
                                        <input type="text" value="<?php echo $enable_money?>" class="form-control" disabled/>
                                        <?php if($conf['recharge']==1){?><div class="input-group-btn"><a href="./recharge.php" class="btn btn-default">充值</a></div><?php }?>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>CSV/Excel文件上传</label>
                                    <input type="file" name="datafile" class="form-control" accept=".csv,.xls,.xlsx" required/>
                                </div>
                                <div class="form-group">
                                    <label>验证密码</label>
                                    <input type="password" name="paypwd" class="form-control" required/>
                                </div>
                                <div class="alert alert-info">
                                    <strong>文件格式说明：</strong><br/>
                                    1. 支持CSV文件和Excel文件（.xls或.xlsx）<br/>
                                    2. 第一列：收款账号（支付宝账号/微信Openid/QQ号码/银行卡号）<br/>
                                    3. 第二列：收款人姓名<br/>
                                    4. 第三列：转账金额（元）<br/>
                                    5. 第四列：转账备注（可选）<br/>
                                    6. 不要包含标题行，直接填写数据<br/>
                                    <br/><br/>
                                    <a href="javascript:void(0)" onclick="downloadTemplate('csv')" class="btn btn-xs btn-info">下载CSV模板</a>
                                </div>
                                <button type="button" id="upload-btn" class="btn btn-primary">开始代付</button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Processing status panel -->
                    <div class="panel panel-default" id="process-panel" style="display:none;">
                        <div class="panel-heading font-bold">
                            处理中... <span id="process-percent" class="badge bg-info">0%</span>
                        </div>
                        <div class="panel-body">
                            <div class="progress progress-striped active">
                                <div id="process-progress" class="progress-bar progress-bar-success" role="progressbar" style="width: 0%">
                                </div>
                            </div>
                            <div id="process-info" class="text-center mb10">文件解析中，请稍候...</div>
                            <div id="process-status" class="text-center">准备处理...</div>
                        </div>
                    </div>
                    
                    <!-- Batch history panel -->
                    <div class="panel panel-default">
                        <div class="panel-heading font-bold">
                            批量代付历史
                        </div>
                        <div class="panel-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>批次ID</th>
                                            <th>类型</th>
                                            <th>总数量</th>
                                            <th>成功/失败/处理中</th>
                                            <th>总金额</th>
                                            <th>批处理时间</th>
                                            <!-- <th>耗时(秒)</th> -->
                                            <th>操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if(is_array($batchList) && count($batchList) > 0){
                                            foreach($batchList as $batch){
                                                $type = '';
                                                if($batch['type'] == 'alipay'){
                                                    $type = '<img src="/assets/icon/alipay.ico" width="16" onerror="this.style.display=\'none\'">支付宝';
                                                }elseif($batch['type'] == 'wxpay'){
                                                    $type = '<img src="/assets/icon/wxpay.ico" width="16" onerror="this.style.display=\'none\'">微信';
                                                }elseif($batch['type'] == 'qqpay'){
                                                    $type = '<img src="/assets/icon/qqpay.ico" width="16" onerror="this.style.display=\'none\'">QQ钱包';
                                                }elseif($batch['type'] == 'bank'){
                                                    $type = '<img src="/assets/icon/bank.ico" width="16" onerror="this.style.display=\'none\'">银行卡';
                                                }
                                                echo '<tr>
                                                    <td>'.$batch['batch_no'].'</td>
                                                    <td>'.$type.'</td>
                                                    <td>'.$batch['total_count'].'</td>
                                                    <td>'.$batch['success_count'].'/'.$batch['fail_count'].'/'.$batch['pending_count'].'</td>
                                                    <td>￥'.$batch['total_amount'].'</td>
                                                    // <td>'.$batch['addtime'].'</td>
                                                    <td><a href="javascript:void(0)" onclick="viewBatchDetails('.$batch['id'].')" class="btn btn-xs btn-info">查看详情</a></td>
                                                </tr>';
                                            }
                                        }else{
                                            echo '<tr><td colspan="8" class="text-center">暂无批量代付记录</td></tr>';
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for details -->
<div class="modal fade" id="batchDetailsModal" tabindex="-1" role="dialog" aria-labelledby="batchDetailsModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="batchDetailsModalLabel">批量代付详情</h4>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-striped" id="batch-details-table">
                        <thead>
                            <tr>
                                <th>交易号</th>
                                <th>收款账号</th>
                                <th>收款人</th>
                                <th>金额</th>
                                <th>状态</th>
                                <th>备注</th>
                            </tr>
                        </thead>
                        <tbody id="batch-details-body">
                        </tbody>
                    </table>
                </div>
                <div class="text-center">
                    <ul class="pagination" id="detail-pagination"></ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">关闭</button>
            </div>
        </div>
    </div>
</div>

<?php include 'foot.php';?>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.min.js"></script>
<script>
var batchId = null;
var processing = false;
var transferRate = <?php echo $conf['transfer_rate']; ?>;
var availableMoney = <?php echo $enable_money; ?>;

// Handle file upload and direct processing
$('#upload-btn').click(function() {
    if (processing) return;
    
    var fileInput = $('input[name="datafile"]');
    var paypwd = $('input[name="paypwd"]').val();
    var app = $('select[name="app"]').val();
    
    if (fileInput[0].files.length === 0) {
        layer.alert('请选择文件', {icon: 2});
        return;
    }
    
    if (paypwd === '') {
        layer.alert('请输入验证密码', {icon: 2});
        return;
    }
    
    // Confirm before starting
    layer.confirm('确认开始批量代付？系统将直接处理文件中的所有转账请求。', {
        btn: ['确认','取消'],
        closeBtn: 0
    }, function(index) {
        layer.close(index);  // Close the dialog explicitly
        processing = true;
        
        $('#batch-upload-form').hide();
        $('#process-panel').show();
        
        // Set initial status
        $('#process-percent').text('0%');
        $('#process-progress').css('width', '0%');
        $('#process-info').text('文件解析中，请稍候...');
        $('#process-status').text('准备处理...');
        
        // Create FormData and upload file
        var formData = new FormData();
        formData.append('datafile', fileInput[0].files[0]);
        formData.append('paypwd', paypwd);
        formData.append('app', app);
        
        $.ajax({
            url: 'ajax2.php?act=batch_transfer_upload',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                if (res.code === 0) {
                    $('#process-info').html('文件解析完成，共 <b>' + res.total_count + '</b> 条记录，总金额: <b>￥' + res.total_amount + '</b>，手续费: <b>￥' + res.total_fee + '</b>');
                    
                    batchId = res.batch_id;
                    
                    // Store the data in memory
                    if (res.data && res.data.length > 0) {
                        processBatchWithData(0, res.total_count, res.data);
                    } else {
                        processBatch(0, res.total_count);
                    }
                } else {
                    processing = false;
                    $('#process-panel').hide();
                    $('#batch-upload-form').show();
                    
                    // 处理余额不足错误
                    if (res.available !== undefined && res.required !== undefined) {
                        layer.alert('余额不足，无法完成批量代付！<br>需要金额：￥' + res.required + '<br>可用余额：￥' + res.available + '<br><br><a href="./recharge.php" class="btn btn-xs btn-info">立即充值</a>', {
                            icon: 2,
                            title: '余额不足'
                        });
                    } else {
                        layer.alert(res.msg, {icon: 2});
                    }
                }
            },
            error: function() {
                processing = false;
                $('#process-panel').hide();
                $('#batch-upload-form').show();
                layer.alert('服务器错误，请稍后再试', {icon: 2});
            }
        });
    });
});

// Process batch with data directly (in memory)
function processBatchWithData(offset, totalCount, dataArray) {
    if (!batchId) return;
    
    var percent = Math.round((offset / totalCount) * 100);
    $('#process-percent').text(percent + '%');
    $('#process-progress').css('width', percent + '%');
    
    if (offset >= totalCount) {
        // All done
        finishBatchProcess();
        return;
    }
    
    var chunkSize = 10;
    var end = Math.min(offset + chunkSize, totalCount);
    $('#process-status').text('正在处理第 ' + (offset + 1) + ' 至 ' + end + ' 条，共 ' + totalCount + ' 条');
    
    // Get current chunk of data
    var chunk = dataArray.slice(offset, end);
    
    $.ajax({
        url: 'ajax2.php?act=batch_transfer_process',
        type: 'POST',
        data: {
            batch_id: batchId,
            data: JSON.stringify(chunk)
        },
        dataType: 'json',
        success: function(res) {
            if (res.code === 0) {
                // Process next chunk
                processBatchWithData(offset + chunkSize, totalCount, dataArray);
            } else {
                processing = false;
                $('#process-panel').hide();
                $('#batch-upload-form').show();
                layer.alert('处理过程中出错: ' + res.msg, {icon: 2});
            }
        },
        error: function() {
            processing = false;
            $('#process-panel').hide();
            $('#batch-upload-form').show();
            layer.alert('服务器错误，请稍后再试', {icon: 2});
        }
    });
}

// Finish batch processing
function finishBatchProcess() {
    $.ajax({
        url: 'ajax2.php?act=batch_transfer_finish',
        type: 'POST',
        data: {
            batch_id: batchId
        },
        dataType: 'json',
        success: function(res) {
            processing = false;
            
            if (res.code === 0) {
                $('#process-percent').text('100%');
                $('#process-progress').css('width', '100%');
                $('#process-status').text('处理完成！成功: ' + res.success_count + ', 失败: ' + res.fail_count + ', 处理中: ' + res.pending_count);
                
                layer.alert('批量代付处理完成！<br>成功: ' + res.success_count + '<br>失败: ' + res.fail_count + 
                           '<br>处理中: ' + res.pending_count + 
                           '<br><br>成功付款金额: ￥' + res.success_amount + 
                           '<br>实际扣除余额: ￥' + res.total_cost, {
                    icon: 1,
                    closeBtn: 0
                }, function() {
                    location.reload();
                });
            } else {
                $('#process-panel').hide();
                $('#batch-upload-form').show();
                layer.alert(res.msg, {icon: 2});
            }
        },
        error: function() {
            processing = false;
            $('#process-panel').hide();
            $('#batch-upload-form').show();
            layer.alert('服务器错误，请稍后再试', {icon: 2});
        }
    });
}

// View batch details with pagination
function viewBatchDetails(id) {
    viewBatchDetailsPage(id, 1);
}

function viewBatchDetailsPage(id, page) {
    var loadIndex = layer.load(2);
    var pageSize = 15; // 15 items per page
    
    $.ajax({
        url: 'ajax2.php?act=batch_transfer_details',
        type: 'GET',
        data: {
            batch_id: id,
            page: page,
            limit: pageSize
        },
        dataType: 'json',
        success: function(res) {
            layer.close(loadIndex);
            
            if (res.code === 0) {
                var html = '';
                
                res.data.forEach(function(item) {
                    var status = '';
                    if (item.status === '0') {
                        status = '<span class="label label-warning">处理中</span>';
                    } else if (item.status === '1') {
                        status = '<span class="label label-success">成功</span>';
                    } else if (item.status === '2') {
                        status = '<span class="label label-danger">失败</span>';
                    }

                    // Display result if available (error message), otherwise description
                    var remark = item.result ? ('<span class="text-danger">' + item.result + '</span>') : (item.desc || '');

                    html += '<tr>';
                    html += '<td>' + item.biz_no + '</td>';
                    html += '<td>' + item.account + '</td>';
                    html += '<td>' + item.username + '</td>';
                    html += '<td>￥' + item.money + '</td>';
                    html += '<td>' + status + '</td>';
                    html += '<td>' + remark + '</td>'; // Display result or desc
                    html += '</tr>';
                });
                
                $('#batch-details-body').html(html);
                
                // Render pagination
                var totalPages = Math.ceil(res.total / pageSize);
                renderDetailPagination(id, page, totalPages);
                
                $('#batchDetailsModal').modal('show');
            } else {
                layer.alert(res.msg, {icon: 2});
            }
        },
        error: function() {
            layer.close(loadIndex);
            layer.alert('服务器错误，请稍后再试', {icon: 2});
        }
    });
}

// Render detail pagination
function renderDetailPagination(id, page, totalPages) {
    var html = '';
    
    if (totalPages <= 1) {
        $('#detail-pagination').html('');
        return;
    }
    
    if (page > 1) {
        html += '<li><a href="javascript:void(0)" onclick="viewBatchDetailsPage(' + id + ', ' + (page - 1) + ')">&laquo;</a></li>';
    } else {
        html += '<li class="disabled"><a href="javascript:void(0)">&laquo;</a></li>';
    }
    
    var startPage = Math.max(1, page - 2);
    var endPage = Math.min(totalPages, page + 2);
    
    for (var i = startPage; i <= endPage; i++) {
        if (i === page) {
            html += '<li class="active"><a href="javascript:void(0)">' + i + '</a></li>';
        } else {
            html += '<li><a href="javascript:void(0)" onclick="viewBatchDetailsPage(' + id + ', ' + i + ')">' + i + '</a></li>';
        }
    }
    
    if (page < totalPages) {
        html += '<li><a href="javascript:void(0)" onclick="viewBatchDetailsPage(' + id + ', ' + (page + 1) + ')">&raquo;</a></li>';
    } else {
        html += '<li class="disabled"><a href="javascript:void(0)">&raquo;</a></li>';
    }
    
    $('#detail-pagination').html(html);
}

// Download template file
function downloadTemplate(type) {
    window.location.href = 'download.php?type=transfer_template&format=' + type;
}

// Process batch in chunks (fallback)
function processBatch(offset, totalCount) {
    if (!batchId) return;
    
    var percent = Math.round((offset / totalCount) * 100);
    $('#process-percent').text(percent + '%');
    $('#process-progress').css('width', percent + '%');
    
    if (offset >= totalCount) {
        // All done
        finishBatchProcess();
        return;
    }
    
    $('#process-status').text('正在处理第 ' + (offset + 1) + ' 至 ' + Math.min(offset + 10, totalCount) + ' 条，共 ' + totalCount + ' 条');
    
    $.ajax({
        url: 'ajax2.php?act=batch_transfer_process',
        type: 'POST',
        data: {
            batch_id: batchId,
            offset: offset,
            limit: 10 // Process 10 at a time
        },
        dataType: 'json',
        success: function(res) {
            if (res.code === 0) {
                // Process next chunk
                processBatch(offset + 10, totalCount);
            } else {
                processing = false;
                $('#process-panel').hide();
                $('#batch-upload-form').show();
                layer.alert('处理过程中出错: ' + res.msg, {icon: 2});
            }
        },
        error: function() {
            processing = false;
            $('#process-panel').hide();
            $('#batch-upload-form').show();
            layer.alert('服务器错误，请稍后再试', {icon: 2});
        }
    });
}
</script> 