<?php

/**
 * @file
 * Place cas_group_profiles blocks where D7 embedded profiles_membership_larch.
 *
 * Each D7 embed becomes a "Group people listing" block, its membership-type
 * checkboxes translated from the D7 display's filter (a 'not in' filter is
 * complemented against the current membership_types terms). The group is not
 * configured: the block's view argument defaults to the page's own group.
 *
 * The target section is resolved by PROVENANCE, not arithmetic: the embed's
 * D7 column item maps (via the field_lp_adj_column migrate map) to its D10
 * inline block, and the listing goes into the section holding that block,
 * weighted with the column's delta so it sorts right behind it. D7 columns
 * whose only content was the view produced no block or section at all; those
 * listings get a fresh section inserted after the nearest earlier delta's
 * section. Existing cas_group_profiles components are cleared per node first,
 * so reruns fully reconcile. Generated from the D7 view export + embed audit.
 *
 * Usage: drush scr scripts-dev/place_profiles_group_membership.php
 */

use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;

$display_map = [
  'block' => ['op' => 'not in', 'tids' => [466], 'grid' => FALSE],
  'block_1' => ['op' => 'in', 'tids' => [], 'grid' => FALSE],
  'block_10' => ['op' => 'in', 'tids' => [281, 282, 283, 284, 288, 374, 289, 290, 293], 'grid' => FALSE],
  'block_11' => ['op' => 'in', 'tids' => [289], 'grid' => FALSE],
  'block_12' => ['op' => 'in', 'tids' => [288], 'grid' => FALSE],
  'block_13' => ['op' => 'in', 'tids' => [441], 'grid' => FALSE],
  'block_14' => ['op' => 'in', 'tids' => [861, 285, 286, 287, 289], 'grid' => FALSE],
  'block_15' => ['op' => 'in', 'tids' => [294], 'grid' => FALSE],
  'block_16' => ['op' => 'in', 'tids' => [294, 291, 292], 'grid' => FALSE],
  'block_17' => ['op' => 'in', 'tids' => [295], 'grid' => FALSE],
  'block_18' => ['op' => 'in', 'tids' => [826, 4936, 281, 282, 283, 293, 290, 374, 284], 'grid' => FALSE],
  'block_19' => ['op' => 'in', 'tids' => [761, 826, 284, 288, 289, 294, 291, 292], 'grid' => FALSE],
  'block_2' => ['op' => 'in', 'tids' => [826], 'grid' => FALSE],
  'block_20' => ['op' => 'in', 'tids' => [441, 288], 'grid' => TRUE],
  'block_21' => ['op' => 'in', 'tids' => [284], 'grid' => FALSE],
  'block_22' => ['op' => 'in', 'tids' => [441], 'grid' => FALSE],
  'block_23' => ['op' => 'in', 'tids' => [3936, 761, 19251, 621], 'grid' => FALSE],
  'block_24' => ['op' => 'in', 'tids' => [296], 'grid' => FALSE],
  'block_25' => ['op' => 'in', 'tids' => [3931, 3936, 761], 'grid' => FALSE],
  'block_26' => ['op' => 'in', 'tids' => [5221], 'grid' => FALSE],
  'block_27' => ['op' => 'in', 'tids' => [621, 292], 'grid' => FALSE],
  'block_28' => ['op' => 'in', 'tids' => [296], 'grid' => FALSE],
  'block_29' => ['op' => 'in', 'tids' => [4346], 'grid' => FALSE],
  'block_3' => ['op' => 'in', 'tids' => [3936, 761, 621], 'grid' => FALSE],
  'block_30' => ['op' => 'in', 'tids' => [4336], 'grid' => FALSE],
  'block_31' => ['op' => 'in', 'tids' => [4341], 'grid' => FALSE],
  'block_32' => ['op' => 'in', 'tids' => [4351], 'grid' => FALSE],
  'block_33' => ['op' => 'in', 'tids' => [826, 4936, 6751, 281, 282, 283, 6761, 6756, 293, 290], 'grid' => FALSE],
  'block_34' => ['op' => 'in', 'tids' => [288], 'grid' => FALSE],
  'block_35' => ['op' => 'in', 'tids' => [374], 'grid' => FALSE],
  'block_36' => ['op' => 'in', 'tids' => [293, 290], 'grid' => FALSE],
  'block_37' => ['op' => 'in', 'tids' => [291], 'grid' => FALSE],
  'block_38' => ['op' => 'in', 'tids' => [761, 826, 4936, 281, 282, 283, 290, 374], 'grid' => FALSE],
  'block_39' => ['op' => 'in', 'tids' => [295], 'grid' => FALSE],
  'block_4' => ['op' => 'in', 'tids' => [3936, 761, 826, 20901, 4936, 6751, 281, 282, 283, 6761, 6756, 293, 290, 374], 'grid' => FALSE],
  'block_40' => ['op' => 'in', 'tids' => [4621], 'grid' => FALSE],
  'block_41' => ['op' => 'in', 'tids' => [284], 'grid' => FALSE],
  'block_42' => ['op' => 'in', 'tids' => [861, 285, 286, 287, 289], 'grid' => FALSE],
  'block_43' => ['op' => 'in', 'tids' => [3931, 3936, 5216, 5221], 'grid' => FALSE],
  'block_44' => ['op' => 'in', 'tids' => [761, 826, 4936, 6751, 281, 282, 283, 6761, 6756, 293, 290, 374], 'grid' => FALSE],
  'block_45' => ['op' => 'in', 'tids' => [294, 291, 292], 'grid' => FALSE],
  'block_46' => ['op' => 'in', 'tids' => [861, 285, 286, 287, 289], 'grid' => FALSE],
  'block_47' => ['op' => 'in', 'tids' => [292], 'grid' => FALSE],
  'block_48' => ['op' => 'in', 'tids' => [4366], 'grid' => FALSE],
  'block_49' => ['op' => 'in', 'tids' => [826, 6751, 281, 282, 283, 6756], 'grid' => FALSE],
  'block_5' => ['op' => 'in', 'tids' => [22061, 294, 291, 292, 4366], 'grid' => FALSE],
  'block_50' => ['op' => 'in', 'tids' => [294], 'grid' => FALSE],
  'block_51' => ['op' => 'in', 'tids' => [3936, 761, 19251, 621, 294], 'grid' => FALSE],
  'block_52' => ['op' => 'in', 'tids' => [294, 291, 292, 4366], 'grid' => FALSE],
  'block_53' => ['op' => 'in', 'tids' => [292], 'grid' => FALSE],
  'block_54' => ['op' => 'in', 'tids' => [18006, 436], 'grid' => FALSE],
  'block_55' => ['op' => 'in', 'tids' => [3936, 761, 826, 20901, 4936, 6751, 281, 282, 283, 6761, 6756, 293, 290, 374, 294], 'grid' => FALSE],
  'block_56' => ['op' => 'in', 'tids' => [22096], 'grid' => FALSE],
  'block_57' => ['op' => 'in', 'tids' => [22101], 'grid' => FALSE],
  'block_58' => ['op' => 'in', 'tids' => [6756], 'grid' => FALSE],
  'block_59' => ['op' => 'in', 'tids' => [6751, 281, 282, 283], 'grid' => FALSE],
  'block_6' => ['op' => 'in', 'tids' => [295], 'grid' => FALSE],
  'block_60' => ['op' => 'in', 'tids' => [3936], 'grid' => FALSE],
  'block_61' => ['op' => 'in', 'tids' => [761], 'grid' => FALSE],
  'block_63' => ['op' => 'in', 'tids' => [621], 'grid' => FALSE],
  'block_64' => ['op' => 'in', 'tids' => [861], 'grid' => FALSE],
  'block_7' => ['op' => 'in', 'tids' => [5216, 20901, 22061, 861, 285, 286, 287, 289], 'grid' => FALSE],
  'block_8' => ['op' => 'in', 'tids' => [294], 'grid' => FALSE],
  'block_9' => ['op' => 'in', 'tids' => [290], 'grid' => FALSE],
  'default' => ['op' => 'not in', 'tids' => [466], 'grid' => FALSE],
];

// [node nid, D7 section delta, D7 column delta (-1 = non-column embed),
//  D7 column item id (0 = n/a), D7 display id].
$placements = [
  [406, 0, 0, 55256, 'block_13'],
  [16834, 0, 0, 46101, 'block_54'],
  [16861, 1, 0, 55391, 'block_4'],
  [16861, 2, 0, 55396, 'block_5'],
  [24257, 0, 0, 48851, 'block_4'],
  [24257, 1, 0, 48856, 'block_12'],
  [24257, 2, 0, 48866, 'block_13'],
  [24257, 3, 0, 48871, 'block_5'],
  [24740, 0, 0, 55156, 'block_54'],
  [25243, 1, 0, 48796, 'block_4'],
  [25364, 0, 0, 46501, 'block_5'],
  [25470, 0, 0, 47611, 'default'],
  [25566, 0, 0, 47616, 'block_23'],
  [25566, 1, 0, 47621, 'block_33'],
  [25566, 2, 0, 47822, 'block_35'],
  [25566, 3, 0, 47626, 'block_21'],
  [25566, 4, 0, 47631, 'block_12'],
  [25567, 0, 0, 47636, 'block_7'],
  [25568, 0, 0, 47651, 'block_5'],
  [25569, 0, 0, 47641, 'block_6'],
  [25569, 1, 0, 105456, 'block_28'],
  [25818, 0, 0, 43681, 'block_4'],
  [25818, 1, 0, 43686, 'block_13'],
  [25818, 2, 0, 43691, 'block_5'],
  [25818, 3, 0, 43696, 'block_6'],
  [25818, 4, 0, 43701, 'block_28'],
  [26625, 0, 0, 45251, 'block_3'],
  [26625, 1, 0, 45256, 'block_7'],
  [30021, 0, 0, 46531, 'block_6'],
  [33696, 0, 0, 82861, 'block_23'],
  [33696, 1, 0, 55621, 'block_5'],
  [33696, 2, 0, 55626, 'block_54'],
  [33696, 3, 0, 55631, 'block_13'],
  [33696, 4, 0, 77941, 'block_7'],
  [38031, 0, 0, 49236, 'block_1'],
  [38526, 1, -1, 0, 'block_4'],
  [38526, 3, -1, 0, 'block_7'],
  [38526, 5, -1, 0, 'block_12'],
  [40926, 0, 0, 53486, 'block_6'],
  [42116, 0, 0, 54671, 'block_7'],
  [42126, 0, 0, 49231, 'block_53'],
  [42136, 0, 0, 49221, 'block_17'],
  [43376, 0, 0, 53096, 'block_13'],
  [45416, 0, 0, 55941, 'block_23'],
  [45416, 1, 0, 55946, 'block_13'],
  [45416, 2, 0, 55951, 'block_12'],
  [45416, 3, 0, 55956, 'block_5'],
  [46181, 0, 0, 42776, 'block_23'],
  [46181, 1, 0, 42781, 'block_53'],
  [46186, 0, 0, 42731, 'block_7'],
  [46191, 2, 0, 42771, 'block_23'],
  [46191, 3, 0, 116526, 'block_57'],
  [46191, 4, 0, 116531, 'block_56'],
  [46191, 5, 0, 116451, 'block_36'],
  [46191, 6, 0, 116536, 'block_59'],
  [46191, 7, 0, 116541, 'block_58'],
  [46191, 8, 0, 42756, 'block_37'],
  [46191, 9, 0, 42761, 'block_21'],
  [46196, 0, 0, 42721, 'block_12'],
  [46600, 0, 0, 44106, 'block_12'],
  [46609, 0, 0, 44096, 'block_13'],
  [54681, 0, 0, 48876, 'block_5'],
  [57756, 0, 0, 42726, 'block_6'],
  [57781, 0, 0, 48836, 'block_7'],
  [57786, 0, 0, 48841, 'block_12'],
  [60181, 0, 0, 46461, 'block_55'],
  [60191, 0, 0, 46476, 'block_21'],
  [60196, 0, 0, 46481, 'block_12'],
  [60201, 0, 0, 46486, 'block_7'],
  [60206, 0, 0, 68366, 'block_23'],
  [60206, 1, 0, 46496, 'block_53'],
  [60206, 2, 0, 101481, 'block_50'],
  [60211, 0, 0, 46516, 'block_6'],
  [62521, 0, 0, 46521, 'block_6'],
  [67301, 0, 0, 46466, 'block_4'],
  [67726, 2, 1, 55231, 'block_20'],
  [80151, 0, 0, 48766, 'block_13'],
  [80261, 0, 0, 47902, 'block_13'],
  [97341, 0, 0, 55216, 'block_13'],
  [107476, 2, 0, 49991, 'block_23'],
  [107476, 3, 0, 49996, 'block_49'],
  [107476, 4, 0, 51106, 'block_36'],
  [107476, 5, 0, 50001, 'block_21'],
  [107476, 6, 0, 50006, 'block_12'],
  [107486, 0, 0, 49981, 'block_7'],
  [107566, 0, 0, 49976, 'block_6'],
  [107566, 1, 0, 51091, 'block_30'],
  [107571, 0, 0, 49986, 'block_5'],
  [108551, 0, 0, 55971, 'block_13'],
  [109371, 1, 0, 55671, 'block_23'],
  [109371, 2, 0, 55676, 'block_7'],
  [109371, 3, 0, 55681, 'block_28'],
  [110361, 0, 0, 47646, 'block_6'],
  [113216, 0, 0, 55326, 'block_4'],
  [114376, 0, 0, 55961, 'block_13'],
  [116191, 0, 0, 44111, 'block_6'],
  [116266, 0, 0, 44101, 'block_13'],
  [215351, 0, 0, 48846, 'block_13'],
  [222186, 0, 0, 95151, 'block_13'],
  [222186, 1, 0, 95156, 'block_6'],
  [230796, 1, -1, 0, 'block_13'],
  [234356, 0, 0, 24861, 'block_13'],
  [234396, 0, 0, 24976, 'block_54'],
  [234396, 1, 0, 92116, 'block_12'],
  [234546, 0, 0, 25336, 'block_23'],
  [234546, 0, 1, 25341, 'block_7'],
  [234546, 0, 2, 25351, 'block_13'],
  [234546, 0, 3, 25346, 'block_5'],
  [235206, 0, 0, 26111, 'block_23'],
  [235206, 0, 1, 26116, 'block_4'],
  [235206, 0, 2, 26121, 'block_7'],
  [235206, 0, 3, 26131, 'block_21'],
  [235206, 0, 4, 26126, 'block_16'],
  [235556, 0, 0, 26601, 'block_4'],
  [235556, 0, 1, 26611, 'block_5'],
  [235556, 0, 2, 26606, 'block_13'],
  [236321, 0, 0, 27506, 'block_7'],
  [236326, 0, 0, 27511, 'block_6'],
  [236331, 0, 0, 27516, 'block_40'],
  [236336, 0, 0, 27521, 'block_5'],
  [236341, 1, 0, 27566, 'block_3'],
  [236341, 2, 0, 27526, 'block_2'],
  [236341, 3, 0, 28771, 'block_36'],
  [236341, 4, 0, 31246, 'block_35'],
  [236341, 5, 0, 27531, 'block_41'],
  [236341, 6, 0, 27541, 'block_12'],
  [236341, 7, 0, 27571, 'block_11'],
  [236621, 0, 0, 28656, 'block_23'],
  [236621, 0, 1, 28661, 'block_4'],
  [236621, 0, 2, 28666, 'block_6'],
  [236621, 0, 3, 100486, 'block_13'],
  [237186, 0, 0, 29531, 'block_13'],
  [238321, 0, 0, 31196, 'default'],
  [238436, 0, 0, 31451, 'block_28'],
  [239091, 0, 0, 32661, 'block_13'],
  [239256, 0, 0, 32976, 'block_23'],
  [239256, 1, 0, 32981, 'block_4'],
  [239256, 2, 0, 32986, 'block_5'],
  [239256, 3, 0, 32991, 'block_34'],
  [240086, 0, 0, 34841, 'block_7'],
  [240091, 0, 0, 34846, 'block_6'],
  [240101, 0, 0, 34856, 'block_53'],
  [240171, 0, 0, 34871, 'block_5'],
  [240461, 0, 0, 35846, 'block_23'],
  [240461, 1, 0, 35851, 'block_4'],
  [240461, 2, 0, 122841, 'block_34'],
  [240461, 3, 0, 122846, 'block_41'],
  [241501, 0, 0, 122286, 'block_23'],
  [241501, 1, 0, 38211, 'block_13'],
  [241901, 0, 0, 39251, 'block_6'],
  [241906, 0, 0, 39256, 'block_4'],
  [243276, 0, 0, 42066, 'block_38'],
  [243276, 1, 0, 42221, 'block_37'],
  [243276, 2, 0, 42226, 'block_53'],
  [243276, 3, 0, 51536, 'block_50'],
  [243281, 0, 0, 42071, 'block_34'],
  [243286, 0, 0, 42076, 'block_6'],
  [243621, 2, 1, 42926, 'block_20'],
  [243961, 3, 1, 43551, 'block_20'],
  [243966, 2, 0, 43601, 'block_20'],
  [243986, 4, 1, 43726, 'block_20'],
  [246556, 4, 1, 46561, 'block_20'],
  [246776, 4, 1, 46956, 'block_20'],
  [246791, 3, 1, 47146, 'block_20'],
  [246926, 6, 1, 55211, 'block_20'],
  [247511, 2, 1, 48761, 'block_20'],
  [256691, 1, 0, 73571, 'block_13'],
  [256691, 2, 0, 122041, 'block_7'],
  [256691, 3, 0, 72911, 'block_6'],
  [256691, 4, 0, 73576, 'block_24'],
  [257011, 0, 0, 74056, 'block_13'],
  [257011, 1, 0, 74186, 'block_7'],
  [257011, 2, 0, 74176, 'block_17'],
  [257011, 3, 0, 74181, 'block_28'],
  [257151, 0, 0, 74276, 'block_13'],
  [257151, 1, 0, 74281, 'block_46'],
  [257151, 2, 0, 74286, 'block_6'],
  [257541, 0, 0, 74631, 'block_54'],
  [257541, 1, 0, 74636, 'block_47'],
  [265801, 2, 0, 90651, 'block_4'],
  [268341, 0, 0, 95971, 'block_13'],
  [268341, 1, 0, 95976, 'block_29'],
  [269151, 1, 0, 98786, 'block_23'],
  [269151, 2, 0, 99566, 'block_42'],
  [269151, 3, 0, 99571, 'block_39'],
  [270916, 0, 0, 103806, 'block_23'],
  [270916, 1, 0, 103191, 'block_54'],
  [270916, 2, 0, 103811, 'block_6'],
  [270916, 3, 0, 107811, 'block_24'],
  [271006, 0, 0, 103196, 'block_4'],
  [282921, 0, 0, 126411, 'block_55'],
  [282921, 1, 0, 128931, 'block_63'],
  [282921, 2, 0, 126431, 'block_13'],
  [282921, 3, 0, 126436, 'block_11'],
  [282921, 4, 0, 126446, 'block_64'],
  [282921, 5, 0, 126381, 'block_47'],
  [282921, 6, 0, 126376, 'block_6'],
  [285081, 0, 0, 132736, 'block_60'],
  [285081, 1, 0, 132751, 'block_57'],
  [285081, 2, 0, 132761, 'block_21'],
  [285081, 3, 0, 132756, 'block_29'],
  [285081, 4, 0, 132741, 'block_12'],
  [285081, 5, 0, 132746, 'block_24'],
  [285256, 0, 0, 132961, 'block_23'],
  [285256, 1, 0, 133551, 'block_35'],
  [285256, 2, 0, 132966, 'block_13'],
  [286496, 1, 0, 134561, 'block_13'],
];

$db = \Drupal::database();
$all_tids = array_map('intval', $db->query("SELECT tid FROM {taxonomy_term_field_data} WHERE vid='membership_types'")->fetchCol());
$col_map = $db->query('SELECT destid1, sourceid1 FROM {migrate_map_field_collection_field_lp_adj_column__to__layout_bu} WHERE destid1 IS NOT NULL')->fetchAllKeyed();

// D7 paragraph structure of nodes with paragraph-hosted embeds: nid =>
// [[delta, item id], ...] (column items listed at their parent's delta).
$para_structure = [
  215 => [[0, 92236], [1, 2806], [2, 2816], [3, 117861], [4, 117376], [5, 117386], [6, 117391], [7, 119676], [8, 117416], [9, 35946], [10, 74201]],
  16132 => [[0, 46066]],
  16845 => [[0, 2171], [1, 2181], [2, 110036], [3, 25076], [4, 25086], [5, 25091], [6, 74456], [7, 68271], [8, 25106]],
  24266 => [[0, 74141], [1, 74151], [2, 74156], [3, 74171]],
  38526 => [[0, 132646], [1, 132631], [2, 132651], [3, 132636], [4, 132656], [5, 132641]],
  46176 => [[0, 13091], [1, 13096], [2, 13101], [3, 73856]],
  87331 => [[0, 8286], [1, 116736], [2, 8296], [3, 130331], [4, 132746], [5, 130686], [6, 132756], [7, 138191], [8, 138196], [9, 25646], [10, 132751], [11, 116721], [12, 117521], [13, 74806], [14, 116726]],
  109801 => [[0, 40421]],
  109811 => [[0, 100516], [0, 106341], [1, 34941], [2, 24226], [3, 34841], [4, 53481]],
  113066 => [[0, 92566], [0, 100856], [1, 100861], [2, 60981], [3, 14096], [4, 25956], [5, 52486], [6, 13736], [7, 13396], [8, 13646], [9, 13651], [10, 37341], [11, 22261]],
  167221 => [[0, 19121], [1, 19126], [2, 19131], [3, 19136], [4, 29626], [5, 28886], [6, 19141]],
  213586 => [[0, 26476], [1, 129706], [2, 129701]],
  230226 => [[0, 47846]],
  230796 => [[0, 18676], [0, 48691], [1, 48696]],
  242761 => [[0, 65281]],
  242866 => [[0, 65321], [1, 65326], [2, 65331], [3, 65336], [4, 65341]],
  264696 => [[0, 98326]],
  265681 => [[0, 99526], [1, 99606], [2, 99586], [3, 99611]],
  283166 => [[0, 127726], [1, 127731], [2, 127736], [3, 127741], [4, 127746], [5, 127751], [6, 127756], [7, 127761], [8, 127766], [9, 127771], [10, 127776], [11, 127781], [12, 127786], [13, 127796]],
];

// Any migrated inline block -> its D7 source item, across every paragraph
// migration (the viewfield/2_column_views bundles were skipped, which is
// exactly why delta arithmetic cannot find their sections).
$para_map = [];
$map_tables = $db->query("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = DATABASE() AND TABLE_NAME LIKE 'migrate\_map\_paragraph\_%'")->fetchCol();
foreach ($map_tables as $table) {
  foreach ($db->query("SELECT destid1, sourceid1 FROM {" . $table . "} WHERE destid1 IS NOT NULL") as $row) {
    $para_map[$row->destid1] = (int) $row->sourceid1;
  }
}
$storage = \Drupal::entityTypeManager()->getStorage('node');
$uuid = \Drupal::service('uuid');

$by_node = [];
foreach ($placements as [$nid, $delta, $col, $col_item, $display]) {
  $by_node[$nid][] = [$delta, $col, $col_item, $display];
}

$placed = $missing_node = $inserted_sections = 0;
foreach ($by_node as $nid => $items) {
  $node = $storage->load($nid);
  if (!$node || !$node->hasField('layout_builder__layout')) {
    print "SKIP $nid: no node/layout\n";
    $missing_node++;
    continue;
  }
  $list = $node->get('layout_builder__layout');

  // Reconcile from scratch: drop every existing people listing, then any
  // now-empty section this script created on a previous run (label 'people').
  foreach ($list->getSections() as $section) {
    foreach ($section->getComponents() as $c) {
      if (($c->get('configuration')['id'] ?? '') === 'cas_group_profiles') {
        $section->removeComponent($c->getUuid());
      }
    }
  }
  for ($si = count($list->getSections()) - 1; $si >= 0; $si--) {
    $section = $list->getSections()[$si];
    if (($section->getLayoutSettings()['label'] ?? '') === 'people' && !$section->getComponents()) {
      $list->removeSection($si);
    }
  }

  // Column item id -> section index, via the inline blocks each section holds.
  $item_section = [];
  foreach ($list->getSections() as $si => $section) {
    foreach ($section->getComponents() as $c) {
      $cfg = $c->get('configuration');
      if (str_starts_with($cfg['id'] ?? '', 'inline_block:') && !empty($cfg['block_revision_id'])) {
        $bid = $db->query('SELECT id FROM {block_content_revision} WHERE revision_id = :r', [':r' => $cfg['block_revision_id']])->fetchField();
        if ($bid && isset($col_map[$bid])) {
          $item_section[(int) $col_map[$bid]] = $si;
        }
        if ($bid && isset($para_map[$bid])) {
          $item_section[$para_map[$bid]] = $si;
        }
      }
    }
  }

  // Sort by (section delta, column delta) so section insertion for skipped
  // columns lands in document order.
  usort($items, fn($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
  foreach ($items as [$delta, $col, $col_item, $display]) {
    $spec = $display_map[$display];
    $tids = $spec['op'] === 'not in' ? array_values(array_diff($all_tids, $spec['tids'])) : $spec['tids'];
    $config = [
      'id' => 'cas_group_profiles',
      'label' => 'People listing',
      'provider' => 'osu_cas_multisite_groups',
      'label_display' => '0',
      'context_mapping' => [],
      'display' => $spec['grid'] ? 'grid' : 'list',
      'membership_types' => array_map('intval', $tids),
      'group_override' => NULL,
    ];
    $sections = $list->getSections();
    $index = $col_item && isset($item_section[$col_item]) ? $item_section[$col_item] : NULL;
    if ($index === NULL && $col < 0) {
      // Paragraph-hosted embed. When the embed's own paragraph became a
      // section (1-column / 2-column bundles: the view sat beside the
      // paragraph's text), the listing goes into that section, after the
      // text. Only section-less bundles (viewfield, 2_column_views) need a
      // fresh section after the nearest earlier paragraph's.
      foreach ($para_structure[$nid] ?? [] as [$pdelta, $pitem]) {
        if ($pdelta === $delta && isset($item_section[$pitem])) {
          $index = $item_section[$pitem];
          break;
        }
      }
    }
    if ($index === NULL && $col < 0) {
      $after = 0;
      foreach ($para_structure[$nid] ?? [] as [$pdelta, $pitem]) {
        if ($pdelta < $delta && isset($item_section[$pitem])) {
          $after = max($after, $item_section[$pitem]);
        }
      }
      $section = new Section('bootstrap_layout_builder:blb_col_1', ['label' => 'people', 'label_display' => 0, 'container' => 'container', 'container_wrapper_classes' => '', 'container_wrapper' => ['bootstrap_styles' => []], 'container_wrapper_bg_color_class' => '', 'container_wrapper_bg_media' => NULL, 'section_classes' => '', 'regions_classes' => ['blb_region_col_1' => 'd-flex flex-wrap'], 'regions_attributes' => ['blb_region_col_1' => []], 'breakpoints' => [], 'layout_regions_classes' => [], 'remove_gutters' => '0']);
      $list->insertSection($after + 1, $section);
      $inserted_sections++;
      foreach ($item_section as $k => $si) {
        if ($si > $after) {
          $item_section[$k] = $si + 1;
        }
      }
      $index = $after + 1;
    }
    if ($index === NULL) {
      // The D7 column held only the view, so it produced no D10 section.
      // Insert one right after the nearest earlier column's section.
      $after = 0;
      foreach ($item_section as $si) {
        if ($si > $after && $si <= $delta) {
          $after = $si;
        }
      }
      // Best anchor: highest section index among earlier-delta siblings.
      $after = 0;
      foreach ($items as [$d2, $c2, $ci2, $disp2]) {
        if ($d2 < $delta && $ci2 && isset($item_section[$ci2])) {
          $after = max($after, $item_section[$ci2]);
        }
      }
      if (!$after) {
        $after = count($sections) - 1;
      }
      $section = new Section('bootstrap_layout_builder:blb_col_1', ['label' => 'people', 'label_display' => 0, 'container' => 'container', 'container_wrapper_classes' => '', 'container_wrapper' => ['bootstrap_styles' => []], 'container_wrapper_bg_color_class' => '', 'container_wrapper_bg_media' => NULL, 'section_classes' => '', 'regions_classes' => ['blb_region_col_1' => 'd-flex flex-wrap'], 'regions_attributes' => ['blb_region_col_1' => []], 'breakpoints' => [], 'layout_regions_classes' => [], 'remove_gutters' => '0']);
      $list->insertSection($after + 1, $section);
      $inserted_sections++;
      // Re-derive the item->section map: indexes after the insert shifted.
      foreach ($item_section as $k => $si) {
        if ($si > $after) {
          $item_section[$k] = $si + 1;
        }
      }
      $index = $after + 1;
    }
    $section = $list->getSections()[$index];
    $existing = $section->getComponents();
    $region = $existing ? reset($existing)->getRegion() : 'blb_region_col_1';
    $component = SectionComponent::fromArray([
      'uuid' => $uuid->generate(),
      'region' => $region,
      'configuration' => $config,
      'additional' => [],
      'weight' => max($col, 0),
    ]);
    // appendComponent() re-weights to last place; restore the column weight
    // so the listing sorts right behind its own column block (stable sort
    // keeps it after the equal-weight heading).
    $section->appendComponent($component);
    $component->setWeight(max($col, 0));
    $placed++;
  }
  $node->setNewRevision(FALSE);
  $node->setSyncing(TRUE);
  $node->save();
}
printf("Placed: %d  Sections inserted: %d  Missing nodes: %d\n", $placed, $inserted_sections, $missing_node);
