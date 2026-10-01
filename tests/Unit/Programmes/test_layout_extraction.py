"""Synthetic coordinates only; no real performer data in repository fixtures."""
import importlib.util
import unittest
from pathlib import Path
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('extract', Path(__file__).resolve().parents[3] / 'app/Features/Programmes/Extraction/extract.py')
extract = importlib.util.module_from_spec(spec)
spec.loader.exec_module(extract)


def words(x, y, value):
    result = []
    for word in value.split():
        result.append((x, y, x + len(word)*5, y+10, word))
        x += len(word)*5 + 4
    return result


class LayoutExtractionTest(unittest.TestCase):
    def parse(self, pages, competition=True):
        with patch.object(extract, 'pages', return_value=pages):
            return extract.extract('unused', competition, 2026)

    def test_competition_columns_duos_gaps_and_results(self):
        page = words(35,20,'Session 1 - 4 September 2026') + words(35,50,'Item 8: Junior Duo')
        page += words(45,80,'1. Dancer A & Dancer B') + words(320,80,'4. Dancer E & Dancer F')
        page += words(45,100,'2. Dancer C & Dancer D') + words(45,130,'1 st') + words(200,130,'2 nd')
        result=self.parse([(1,600,800,page,False)])
        items=[n for n in result['nodes'] if n['kind']=='item']
        self.assertEqual(['1','2','4'],[n['number'] for n in items])
        self.assertEqual('Dancer A & Dancer B',items[0]['title'])
        self.assertEqual('2026-09-04',result['nodes'][0]['date'])

    def test_table_continues_across_pages_and_unnumbered_awards_are_not_sections(self):
        first=words(30,20,'20.06.26')+words(30,50,'SECTION')+words(130,50,'COMPETITOR #')+words(250,50,'Junior Jazz')
        first+=words(30,80,'10')+words(130,80,'1')+words(250,80,'Dancer A')
        second=words(130,20,'3')+words(250,20,'Dancer B')+words(130,50,'ADJUDICATION')
        second+=words(30,80,'SECTION')+words(130,80,'COMPETITOR #')+words(250,80,'Champion awards')
        second+=words(130,100,'1')+words(250,100,'Award recipient')
        result=self.parse([(1,600,800,first,False),(2,600,800,second,False)])
        self.assertEqual(['10'],[n['number'] for n in result['nodes'] if n['kind']=='section'])
        self.assertEqual(['Dancer A','Dancer B'],[n['title'] for n in result['nodes'] if n['kind']=='item'])
        self.assertTrue(any('no section number' in i for i in result['issues']))

    def test_unnumbered_pairs_and_interval_are_in_order(self):
        page=words(200,20,'Part 1')+words(30,60,'Opening')+words(350,60,'Senior Jazz')
        page+=words(30,100,'Long title')+words(350,100,'Teen Ballet')+words(200,150,'INTERMISSION')
        result=self.parse([(1,600,800,page,False)],False)
        items=[n for n in result['nodes'] if n['kind']=='item']
        self.assertEqual(['Opening','Long title','INTERMISSION'],[n['title'] for n in items])
        self.assertEqual('Teen Ballet',items[1]['class_name'])
        self.assertEqual('break',items[2]['item_kind'])

    def test_unnumbered_scanned_columns_do_not_require_a_readable_act_heading(self):
        page = []
        for i, (title, classes) in enumerate([('Opening Dance','Opening'),('Candy Shop','Tiny Tot Jazz'),('Autumn Leaves','Petite Lyrical'),('Queen','Adult Tap')]):
            page += [(x,y,right,y+24,value) for x,y,right,bottom,value in words(30, 40+i*40, title)]
            page += words(350, 50+i*40, classes)
        page += words(250, 220, 'Interval')
        result = self.parse([(1,600,800,page,True)],False)
        items = [n for n in result['nodes'] if n['kind']=='item']
        self.assertEqual(['Opening Dance','Candy Shop','Autumn Leaves','Queen','Interval'],[n['title'] for n in items])
        self.assertEqual([None]*5,[n['number'] for n in items])
        self.assertEqual('Tiny Tot Jazz',items[1]['class_name'])
        self.assertTrue(any('unnumbered title/class list' in i for i in result['issues']))

    def test_wide_title_spacing_is_not_mistaken_for_the_class_column(self):
        row = words(30,40,'Do You Wanna Build') + words(250,40,'A Snowman') + words(340,40,'Mini Lyrical')
        self.assertEqual(('Do You Wanna Build A Snowman','Mini Lyrical'),extract.concert_pair(row,600))

    def test_isolated_class_mentions_on_credits_pages_are_not_imported(self):
        page = words(30,30,'Special thanks')+words(350,30,'Junior Jazz')
        page += words(30,70,'Our cast and sponsors made this show possible')
        self.assertEqual([],self.parse([(1,600,800,page,False)],False)['nodes'])

    def test_side_by_side_acts_keep_order_classes_and_wrapped_lines(self):
        page = words(170,20,'ACT 1') + words(650,20,'ACT 2')
        page += words(30,60,'Opening') + words(210,60,'Senior Contemporary')
        page += words(30,90,'5,6,7,8') + words(210,90,'Bopper Jazz')
        page += words(30,120,'Story Time') + words(210,120,'Advanced Ballet')
        page += words(210,138,'Senior Ballet') + words(210,156,'Pointe')
        page += words(30,190,'Goodnight') + words(210,190,'Elite Acro')
        page += words(510,60,'Curtain Up') + words(710,60,'Senior Jazz')
        page += words(510,100,'New Day') + words(710,100,'Junior Hip Hop')
        page += words(710,118,'Senior Hip Hop')
        page += words(510,160,'Magic') + words(710,160,'FINALE')
        page += words(710,178,'PRESENTING ALL PERFORMERS')
        result = self.parse([(1,1000,800,page,False)],False)
        items = [n for n in result['nodes'] if n['kind']=='item']
        self.assertEqual(['Opening','5,6,7,8','Story Time','Goodnight','Curtain Up','New Day','Magic'],[n['title'] for n in items])
        self.assertEqual(['Senior Contemporary','Bopper Jazz','Advanced Ballet Senior Ballet Pointe','Elite Acro','Senior Jazz','Junior Hip Hop Senior Hip Hop','FINALE PRESENTING ALL PERFORMERS'],[n['class_name'] for n in items])
        self.assertEqual(['ACT 1','ACT 2'],[n['title'] for n in result['nodes'] if n['kind']=='group'])
        self.assertNotEqual(items[0]['parent_uuid'],items[4]['parent_uuid'])
        self.assertEqual('finale',items[-1]['item_kind'])
        self.assertEqual([1],result['pages'])
        self.assertFalse(any('No running-order' in issue for issue in result['issues']))

    def test_side_by_side_numbered_acts_preserve_printed_number_resets(self):
        page = words(170,20,'ACT 1') + words(650,20,'ACT 2')
        page += words(30,60,'1. First Dance') + words(250,60,'Senior Jazz')
        page += words(30,100,'2. Last Dance') + words(250,100,'Junior Ballet')
        page += words(510,60,'1. Another Dance') + words(750,60,'Open Jazz')
        page += words(510,100,'2. Closing') + words(750,100,'Everyone')
        result = self.parse([(1,1000,800,page,False)],False)
        items = [n for n in result['nodes'] if n['kind']=='item']
        self.assertEqual(['First Dance','Last Dance','Another Dance','Closing'],[n['title'] for n in items])
        self.assertEqual(['1','2','1','2'],[n['number'] for n in items])
        self.assertEqual(['Senior Jazz','Junior Ballet','Open Jazz','Everyone'],[n['class_name'] for n in items])

    def test_compact_concert_rows_split_trailing_class_names(self):
        page=words(30,20,'1. OPENING NUMBER')
        page+=words(30,50,"2. LET'S GET CRAZY PETITE ACRO 1 (FRI)")
        page+=words(30,80,'3. ICON INTERMEDIATE HIP HOP')
        page+=words(30,110,'4. WARRIOR SENIOR LYRICAL')
        page+=words(30,140,'5. COTTON EYE JOE ACRO 1 (TUES & THURS)')
        page+=words(30,170,'6. DIAMONDS INT & SEN AERIALS (SAT)')
        page+=words(30,200,'7. SHAKE IT OFF BALLET (ALL AGES)')
        page+=words(30,230,'8. MIRAGE ACRO 2 (FRI) & PETITE AERIALS (FRI)')
        result=self.parse([(1,600,800,page,False)],False)
        items=[n for n in result['nodes'] if n['kind']=='item']
        self.assertEqual(['OPENING NUMBER', "LET'S GET CRAZY", 'ICON', 'WARRIOR', 'COTTON EYE JOE', 'DIAMONDS', 'SHAKE IT OFF', 'MIRAGE'],[n['title'] for n in items])
        self.assertEqual([None, 'PETITE ACRO 1 (FRI)', 'INTERMEDIATE HIP HOP', 'SENIOR LYRICAL', 'ACRO 1 (TUES & THURS)', 'INT & SEN AERIALS (SAT)', 'BALLET (ALL AGES)', 'ACRO 2 (FRI) & PETITE AERIALS (FRI)'],[n.get('class_name') for n in items])

    def test_missing_year_is_explicit_and_date_resets_do_not_merge_days(self):
        pages=[]
        for p,date,section in [(1,'Friday, 4th September',1),(2,'Friday, 11th September',2)]:
            data=words(35,20,date)+words(35,50,f'{section}. Junior Years Under Jazz')+words(70,80,'1. Example Dancer')
            pages.append((p,600,800,data,False))
        result=self.parse(pages)
        self.assertEqual(['2026-09-04','2026-09-11'],[n['date'] for n in result['nodes'] if n['kind']=='day'])
        self.assertTrue(any('event year' in i for i in result['issues']))

    def test_concert_inline_schedules_keep_wrapped_text_and_ignore_oversized_footer_dates(self):
        page = words(30,40,'1. Junior Jazz - Saturday 12pm-tpm')
        page += words(30,80,'2. Ballet Story - PPID Wednesday') + words(360,80,'5-6pm Pre &')
        page += words(45,100,'Grade 3 Wednesday 4-5pm')
        page += words(30,740,'3. Acro - Saturday 10-1lam')
        page += words(30,770,'4. Finale - Everyone')
        page += [(400,730,425,780,'06'), (435,730,460,780,'12'), (470,730,560,780,'2025')]
        page += words(426,765,'.') + words(461,765,'.')
        result = self.parse([(1,600,800,page,True)],False)
        items = [n for n in result['nodes'] if n['kind']=='item']
        self.assertEqual(['Junior Jazz','Ballet Story','Acro','Finale'],[n['title'] for n in items])
        self.assertEqual(['Saturday 12pm-1pm','PPID Wednesday 5-6pm Pre & Grade 3 Wednesday 4-5pm','Saturday 10-11am','Everyone'],[n['class_name'] for n in items])
        self.assertEqual(['1','2','3','4'],[n['number'] for n in items])
        self.assertEqual('finale',items[-1]['item_kind'])

    def test_conflicting_session_times_are_reported(self):
        first=words(35,20,'26 June 2026 Session 1 - 6.30pm')
        second=words(35,20,'26 June 2026 Session 1 - 6.30am')+words(35,50,'Item 1: Junior Jazz')+words(50,80,'1. Example Dancer')
        result=self.parse([(1,600,800,first,False),(2,600,800,second,False)])
        self.assertTrue(any('conflicting printed times' in i for i in result['issues']))


if __name__=='__main__':
    unittest.main()
