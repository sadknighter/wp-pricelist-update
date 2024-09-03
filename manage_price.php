<?
define( 'WP_DEBUG' , true); define( 'WP_DEBUG_DISPLAY' , true); 
function get_post_by_title($post_title) {
	$categories = get_categories();
	$filteredCategories = wp_list_filter( $categories, [ 'slug' => 'katalog' ] );
	
    $post = get_posts([
		'category'		=> $filteredCategories[0]["cat_ID"],
		'numberposts'	=> 1,
		'post_type'  => 'post',
		'title' => $post_title,
	]);

	if ($post != null && count($post) > 0)
	{
		return $post[array_key_first($post)];
	}
	
    return null;
}

function getPricePageID() {
	$pages = get_pages();
	$filteredPages = wp_list_filter( $pages, [ 'post_name' => 'pricelist' ] );
	$priceListPageId = -1;
	if ($filteredPages != null && count($filteredPages) == 1) {
		$priceListPageId = $filteredPages[array_key_first($filteredPages)]->ID;
	}
	
	return $priceListPageId;
}

function updatePost($postId, $newPrice, $priceFileRowGoodsStatus)
{
	try {
		//update_field( 'availability', $newStatus, $postId );
		update_field( 'price', $newPrice, $postId );
		update_field('availability', $priceFileRowGoodsStatus, $postId);
		return true;
	} catch (Exception $e) {
		echo 'Выброшено исключение: ',  $e->getMessage(), "\n";
		return false;
	}
}

function transliterateText($textCyrrillic) {
    $cyr = ['Љ', 'Њ', 'Џ', 'џ', 'ш', 'ђ', 'ч', 'ћ', 'ж', 'љ', 'њ', 'Ш', 'Ђ', 'Ч', 'Ћ', 'Ж','Ц','ц', 'а','б','в','г','д','е','ё','ж','з','и','й','к','л','м','н','о','п', 'р','с','т','у','ф','х','ц','ч','ш','щ','ъ','ы','ь','э','ю','я', 'А','Б','В','Г','Д','Е','Ё','Ж','З','И','Й','К','Л','М','Н','О','П', 'Р','С','Т','У','Ф','Х','Ц','Ч','Ш','Щ','Ъ','Ы','Ь','Э','Ю','Я'
    ];
    $lat = ['Lj', 'Nj', 'Dž', 'dž', 'š', 'đ', 'č', 'ć', 'ž', 'lj', 'nj', 'Š', 'Đ', 'Č', 'Ć', 'Ž','C','c', 'a','b','v','g','d','e','io','zh','z','i','y','k','l','m','n','o','p', 'r','s','t','u','f','h','ts','ch','sh','sht','a','i','y','e','yu','ya', 'A','B','V','G','D','E','Io','Zh','Z','I','Y','K','L','M','N','O','P', 'R','S','T','U','F','H','Ts','Ch','Sh','Sht','A','I','Y','e','Yu','Ya'
    ];
    return str_replace($cyr, $lat, $textCyrrillic);
}

function insertCategory($categoryName, $catParentId = '')
{
	$categoryName = trim($categoryName);
	$category_id = get_cat_ID($categoryName);
	if (!$category_id){	
		$transliterateUrl = transliterateText($categoryName);
		$wpdocs_cat = array('cat_name' => $categoryName, 'category_description' => '', 'category_nicename' => $transliterateUrl, 'category_parent' => $catParentId);

		// Create the category
		return wp_insert_category($wpdocs_cat);
	}

	return $category_id;
}

function insertPost($parentCategoryName, $categoryName, $postName, $price)
{
	try {
		$katalogId = get_cat_ID("Каталог");
        $parentCategoryId = insertCategory($parentCategoryName);
        $categoryId = insertCategory($categoryName, $parentCategoryId);

        $post_data = array(
            'post_title'    => sanitize_text_field( trim($postName) ),
            'post_content'  => '',
            'post_status'   => 'publish',
            'post_author'   => 1,
            'post_category' => array( $katalogId, $categoryId, $parentCategoryId )
        );
        
        // Вставляем запись в базу данных
        $postId = wp_insert_post( $post_data );

		update_field( 'price', $price, $postId );

        return get_permalink($postId);
	} catch (Exception $e) {
		echo 'Выброшено исключение: ',  $e->getMessage(), "\n";
		return false;
	}
}

function checkValidPriceFile($firstRow) {
	return $firstRow[0] == 'Родительская рубрика' && $firstRow[1] == 'Рубрика' && $firstRow[2] == 'Название' && $firstRow[3] == 'Цена, руб';
}

$priceListPageId = getPricePageID();
?>
	<h2><? echo get_admin_page_title(); ?></h2>
<?

if( wp_verify_nonce( $_POST['fileup_nonce'], 'my_file_upload' ) ) {

	if ( ! function_exists( 'wp_handle_upload' ) )
		require_once( ABSPATH . 'wp-admin/includes/file.php' );

	$file = & $_FILES['my_file_upload'];
	$movefile = wp_handle_upload( $file, [ 'test_form' => false ] );

	if ( $movefile && empty($movefile['error']) ) {
		echo '<p>' . (new \DateTime())->format('Y-m-d H:i:s') . ': Файл был успешно загружен.</p>';
		echo '<p>' . (new \DateTime())->format('Y-m-d H:i:s') . ': Путь: ' . '<a href="' . $movefile["url"] . '">к файлу</a></p>';
		
		if ( defined('CBXPHPSPREADSHEET_PLUGIN_NAME') && file_exists( CBXPHPSPREADSHEET_ROOT_PATH . 'lib/vendor/autoload.php' ) ) {
			//Include PHPExcel
			require_once( CBXPHPSPREADSHEET_ROOT_PATH . 'lib/vendor/autoload.php' );

			//now take instance
			$objPHPExcel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
			$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($movefile["file"]);
			$result = $spreadsheet->getActiveSheet()->toArray();
			
			if (count($result) > 1) {
				$firstRow = $result[0];
				
				if (!checkValidPriceFile($firstRow)) {
					echo '<p>' . (new \DateTime())->format('Y-m-d H:i:s') . ': Файл имеет некорректную структуру. <b>Перезагрузите страницу и попробуйте снова</b></p>';
				} else {
					// we update price link
					update_field( 'price_list_link', $movefile["url"], $priceListPageId );
					
					echo '<p>' . (new \DateTime())->format('Y-m-d H:i:s') . ': Ссылка на файл успешно сохранена на странице <a href="https://mnogocvetik.ru/pricelist/">Прайс</a>.</p>';
					
					foreach ($result as $key=>$value) {
						if ($key == 0 || $value[2] == "") {
							continue;
						}
						
						// We get file row values
                        $priceFileRowParentCategoryName = trim($value[0]);
                        $priceFileRowCategoryName = trim($value[1]);
						$priceFileRowGoodsName = trim($value[2]);
						$priceFileRowGoodsPrice = $value[3];
						$priceFileRowGoodsStatus = trim($value[4]);

						$postFromDB = get_post_by_title($priceFileRowGoodsName);
						
						if ($postFromDB != null) {
							echo '<p>' . (new \DateTime())->format('Y-m-d H:i:s') . ': Найдено совпадение строки прайса с каталогом по имени товара: "' . $priceFileRowGoodsName . '"</p>';
							
							// Update post related to row from file ($postFromDB)
							$resultUpdate = updatePost($postFromDB->ID, $priceFileRowGoodsPrice, $priceFileRowGoodsStatus);
							
							if ($resultUpdate) {
								echo '<p>' . (new \DateTime())->format('Y-m-d H:i:s') . ': Для товара установлен статус: "' . $priceFileRowGoodsStatus . '"</p>';
								echo '<p>' . (new \DateTime())->format('Y-m-d H:i:s') . ': Для товара установлена новая цена: "' . $priceFileRowGoodsPrice . '"</p>';
							} else {
								echo '<p>' . (new \DateTime())->format('Y-m-d H:i:s') . ': Ошибка обновления товара: "' . $priceFileRowGoodsName . '", данные не обновлены.</p>';
							}
						} else {
							echo '<p>' . (new \DateTime())->format('Y-m-d H:i:s') . ': Не найдено совпадение строки прайса с каталогом по имени товара: "' . $priceFileRowGoodsName . '". Будет создан новый товар.</p>';
                            
                            $resultInsertLink = insertPost($priceFileRowParentCategoryName, $priceFileRowCategoryName, $priceFileRowGoodsName, $priceFileRowGoodsPrice);

                            if ($resultInsertLink) {
                                echo '<p>' . (new \DateTime())->format('Y-m-d H:i:s') . ': Ссылка на созданный товар <a href="' . $resultInsertLink . '">' . $priceFileRowGoodsName . '</a>"</p>';
                                echo '<p>' . (new \DateTime())->format('Y-m-d H:i:s') . ': Для товара "' . $priceFileRowGoodsName . '" установлен статус:"' . $priceFileRowGoodsStatus . '"</p>';
								echo '<p>' . (new \DateTime())->format('Y-m-d H:i:s') . ': Для товара "' . $priceFileRowGoodsName . '" установлена новая цена: "' . $priceFileRowGoodsPrice . '"</p>';
                            }
                        }
						
						echo '<p>-------------------------</p>';
					}
				}
			}
		}
	} else {
		echo '<p>' . (new \DateTime())->format('Y-m-d H:i:s') . ': Ошибка при загрузке файла!</p>';
	}

} else {
?>
  <div>
	  <a class="link-7" href="<?=get_field('price_list_link', $priceListPageId );?>">Текущий прайc</a>
	  на <?=get_field('year', $priceListPageId);?> г. 
  </div>
  <div>
	  <p>
		  Форма позволяет обновить файл прайса и статус и цену товаров в каталоге
	  </p>
	  <form enctype="multipart/form-data" action="" method="POST">
	<?php wp_nonce_field( 'my_file_upload', 'fileup_nonce' ); ?>
	<label for="file">Выберите прайс для загрузки (формат .xls)</label>
	<input name="my_file_upload" type="file" />
		  </br>
	<input type="submit" value=" Обновить прайс и каталог" />
</form>
	</div>
</div>
<div class="clear"></div>
<?}?>
